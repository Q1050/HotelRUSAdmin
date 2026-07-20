<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountingExportBatch;
use App\Models\AccountingMapping;
use App\Models\AccountingSyncRun;
use App\Models\NightAudit;
use App\Services\Finance\AccountingExporter;
use App\Services\Operations\IntegrationCircuit;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(AccountingExporter $exporter): Response
    {
        $profile = $exporter->profile();

        $configuration = $profile->configuration ?? [];

        return Inertia::render('Accounting/Index', ['profile' => ['id' => $profile->id, 'name' => $profile->name, 'driver' => $profile->driver, 'active' => $profile->active, 'configuration' => ['company_name' => $configuration['company_name'] ?? '', 'company_id' => $configuration['company_id'] ?? '', 'webhook_url' => $configuration['webhook_url'] ?? '', 'schedule' => $configuration['schedule'] ?? 'manual', 'sync_mode' => $configuration['sync_mode'] ?? 'daily_summary', 'has_secret' => filled($configuration['secret'] ?? null)], 'mappings' => $profile->mappings->map(fn ($map) => ['key' => $map->key, 'code' => $map->account_code, 'name' => $map->account_name])], 'audits' => NightAudit::where('status', 'closed')->latest('business_date')->limit(60)->get()->map(fn ($audit) => ['id' => $audit->id, 'date' => $audit->business_date->toDateString(), 'revenue' => (float) $audit->room_revenue, 'payments' => (float) $audit->payments, 'batchId' => AccountingExportBatch::where('night_audit_id', $audit->id)->whereNull('reversal_of_id')->latest()->value('id')]), 'batches' => AccountingExportBatch::with('reversalOf')->latest('business_date')->latest()->limit(100)->get()->map(fn ($batch) => $this->batchData($batch)), 'syncRuns' => AccountingSyncRun::with('batch')->latest()->limit(100)->get()->map(fn ($run) => ['id' => $run->id, 'provider' => $run->provider, 'status' => $run->status, 'batch' => $run->batch?->batch_number, 'records' => $run->records_sent, 'message' => $run->message, 'externalReference' => $run->external_reference, 'createdAt' => $run->created_at?->toIso8601String(), 'finishedAt' => $run->finished_at?->toIso8601String()])]);
    }

    public function settings(Request $request, AccountingExporter $exporter): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'active' => ['required', 'boolean'], 'mappings' => ['required', 'array'], 'mappings.*.key' => ['required', Rule::in(array_keys(AccountingExporter::DEFAULTS))], 'mappings.*.code' => ['required', 'string', 'max:50'], 'mappings.*.name' => ['required', 'string', 'max:150']]);
        $profile = $exporter->profile();
        $profile->update(['name' => $data['name'], 'active' => $data['active']]);
        foreach ($data['mappings'] as $map) {
            AccountingMapping::updateOrCreate(['accounting_profile_id' => $profile->id, 'key' => $map['key']], ['account_code' => $map['code'], 'account_name' => $map['name']]);
        }
        AuditLogger::record($request, 'accounting_profile_updated', 'finance', 'critical', 'Accounting mappings updated.', $profile);

        return back()->with('success', 'Accounting profile saved.');
    }

    public function integration(Request $request, AccountingExporter $exporter): RedirectResponse
    {
        $data = $request->validate(['driver' => ['required', Rule::in(['file', 'custom_webhook', 'quickbooks', 'xero', 'sage'])], 'company_name' => ['nullable', 'string', 'max:150'], 'company_id' => ['nullable', 'string', 'max:150'], 'webhook_url' => ['nullable', 'required_if:driver,custom_webhook', 'url', 'max:1000'], 'secret' => ['nullable', 'string', 'max:4000'], 'schedule' => ['required', Rule::in(['manual', 'after_night_audit', 'daily'])], 'sync_mode' => ['required', Rule::in(['daily_summary', 'individual_transactions'])]]);
        $profile = $exporter->profile();
        $configuration = collect($data)->except('driver')->all();
        if (blank($configuration['secret'] ?? null)) {
            $configuration['secret'] = data_get($profile->configuration, 'secret');
        }
        $profile->update(['driver' => $data['driver'], 'configuration' => $configuration]);
        AuditLogger::record($request, 'accounting_integration_updated', 'finance', 'critical', "Accounting integration changed to {$data['driver']}.", $profile);

        return back()->with('success', 'Accounting integration settings saved.');
    }

    public function sync(Request $request, AccountingExportBatch $batch, AccountingExporter $exporter, IntegrationCircuit $circuit): RedirectResponse
    {
        abort_unless($batch->status === 'posted', 422, 'Only posted journals can be synchronized.');
        $profile = $exporter->profile();
        abort_unless($profile->active, 422, 'The accounting profile is inactive.');
        $configuration = $profile->configuration ?? [];
        $idempotencyKey = hash('sha256', "accounting-sync:{$profile->driver}:{$batch->id}:{$batch->checksum}");
        $run = AccountingSyncRun::firstOrCreate(['idempotency_key' => $idempotencyKey], ['accounting_profile_id' => $profile->id, 'accounting_export_batch_id' => $batch->id, 'requested_by' => $request->user()->id, 'provider' => $profile->driver, 'status' => 'running', 'started_at' => now()]);
        if (! $run->wasRecentlyCreated && in_array($run->status, ['completed', 'running'])) {
            return back()->with('success', $run->status === 'completed' ? 'This exact journal was already synchronized.' : 'This journal synchronization is already running.');
        }
        if (! $run->wasRecentlyCreated) {
            $run->update(['requested_by' => $request->user()->id, 'status' => 'running', 'message' => null, 'started_at' => now(), 'finished_at' => null]);
        }
        $batch->load('entries');
        $payload = ['property' => app('currentHotel')->only(['id', 'name']), 'profile' => ['name' => $profile->name, 'company_id' => $configuration['company_id'] ?? null, 'sync_mode' => $configuration['sync_mode'] ?? 'daily_summary'], 'batch' => $this->batchData($batch), 'entries' => $batch->entries->map->only(['line_number', 'account_key', 'account_code', 'account_name', 'description', 'reference', 'debit', 'credit'])];

        if ($profile->driver === 'file') {
            $run->update(['status' => 'completed', 'records_sent' => $batch->entries->count(), 'message' => 'Journal prepared for vendor-neutral file export.', 'finished_at' => now()]);
        } elseif ($profile->driver === 'custom_webhook') {
            try {
                $response = $circuit->execute('accounting', 'custom_webhook', 'Custom webhook accounting', fn () => Http::acceptJson()->withToken((string) ($configuration['secret'] ?? ''))->timeout(20)->post((string) ($configuration['webhook_url'] ?? ''))->throw());
                $run->update(['status' => 'completed', 'records_sent' => $batch->entries->count(), 'external_reference' => $response->json('id') ?? $response->header('X-Request-Id'), 'message' => 'Webhook accepted the journal.', 'finished_at' => now()]);
            } catch (\Throwable $exception) {
                $run->update(['status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 2000), 'finished_at' => now()]);
            }
        } else {
            $run->update(['status' => 'authorization_required', 'message' => 'OAuth authorization must be completed before this provider can receive journals.', 'finished_at' => now()]);
        }
        $run->refresh();
        AuditLogger::record($request, 'accounting_sync_requested', 'finance', 'critical', "Journal {$batch->batch_number} sync finished with status {$run->status}.", $run);

        return back()->with($run->status === 'failed' ? 'error' : 'success', 'Accounting sync status: '.str_replace('_', ' ', $run->status).'.');
    }

    public function generate(Request $request, NightAudit $audit, AccountingExporter $exporter): RedirectResponse
    {
        $batch = $exporter->generate($audit, $request->user()->id);
        AuditLogger::record($request, 'accounting_batch_generated', 'finance', 'critical', "Journal {$batch->batch_number} generated.", $batch);

        return back()->with('success', 'Balanced journal generated.');
    }

    public function post(Request $request, AccountingExportBatch $batch, AccountingExporter $exporter): RedirectResponse
    {
        $exporter->post($batch, $request->user()->id);
        AuditLogger::record($request, 'accounting_batch_posted', 'finance', 'critical', "Journal {$batch->batch_number} posted and period locked.", $batch);

        return back()->with('success', 'Journal posted and accounting period locked.');
    }

    public function reverse(Request $request, AccountingExportBatch $batch, AccountingExporter $exporter): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']])['reason'];
        $reversal = $exporter->reverse($batch, $request->user()->id);
        AuditLogger::record($request, 'accounting_batch_reversed', 'finance', 'critical', "Journal {$batch->batch_number} reversed.", $reversal, $reason);

        return back()->with('success', 'Reversal journal created and posted.');
    }

    public function download(AccountingExportBatch $batch, string $format): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'json']), 404);
        $batch->load('entries');
        if ($format === 'json') {
            return response(json_encode(['batch' => $this->batchData($batch), 'entries' => $batch->entries->map->only(['line_number', 'account_key', 'account_code', 'account_name', 'description', 'reference', 'debit', 'credit'])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="'.$batch->batch_number.'.json"']);
        }
        $csv = "Line,Account code,Account name,Description,Reference,Debit,Credit\n";
        foreach ($batch->entries as $entry) {
            $csv .= collect([$entry->line_number, $entry->account_code, $entry->account_name, $entry->description, $entry->reference, $entry->debit, $entry->credit])->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(',')."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$batch->batch_number.'.csv"']);
    }

    private function batchData(AccountingExportBatch $batch): array
    {
        return ['id' => $batch->id, 'number' => $batch->batch_number, 'date' => $batch->business_date->toDateString(), 'status' => $batch->status, 'debits' => (float) $batch->debit_total, 'credits' => (float) $batch->credit_total, 'checksum' => $batch->checksum, 'reversalOf' => $batch->reversalOf?->batch_number, 'generatedAt' => $batch->generated_at?->toIso8601String(), 'postedAt' => $batch->posted_at?->toIso8601String()];
    }
}
