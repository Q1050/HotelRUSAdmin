<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\CreateHotelBackup;
use App\Models\AuditEvent;
use App\Models\HotelBackup;
use App\Models\SecuritySetting;
use App\Services\Backups\HotelBackupManager;
use App\Services\Operations\OperationsMonitor;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response as Download;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityController extends Controller
{
    public function index(Request $request, OperationsMonitor $monitor): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:50'], 'severity' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $query = $this->filtered($filters);
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->latest('failed_at')->limit(20)->get()->map(fn ($job) => $this->failedJobData($job)) : collect();

        $hotel = $request->user()->hotel;
        $oldestJob = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;
        $backups = Schema::hasTable('hotel_backups') ? HotelBackup::withoutGlobalScopes()->where('hotel_id', $hotel->id)->latest()->limit(10)->get()->map(fn ($backup) => ['id' => $backup->id, 'status' => $backup->status, 'sizeBytes' => $backup->size_bytes, 'records' => data_get($backup->manifest, 'records', 0), 'files' => data_get($backup->manifest, 'files', 0), 'missingFiles' => count(data_get($backup->manifest, 'missing_files', [])), 'createdAt' => $backup->created_at?->toISOString(), 'verifiedAt' => $backup->verified_at?->toISOString(), 'error' => $backup->error]) : collect();

        return Inertia::render('Dashboard/Security/Security', ['events' => $query->with('actor')->paginate(30)->withQueryString()->through(fn ($e) => $this->data($e)), 'categories' => AuditEvent::distinct()->orderBy('category')->pluck('category'), 'filters' => array_merge(['search' => '', 'category' => '', 'severity' => '', 'from' => '', 'to' => ''], $filters), 'stats' => ['critical' => AuditEvent::where('severity', 'critical')->where('occurred_at', '>=', now()->subDays(7))->count(), 'warnings' => AuditEvent::where('severity', 'warning')->where('occurred_at', '>=', now()->subDays(7))->count(), 'failedLogins' => AuditEvent::where('action', 'login_failed')->where('occurred_at', '>=', now()->subDay())->count(), 'remoteUnlocks' => AuditEvent::where('action', 'remote_unlock')->where('occurred_at', '>=', now()->subDay())->count()], 'failedJobs' => $failedJobs, 'queueStats' => ['pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0, 'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0, 'oldestMinutes' => $oldestJob ? max(0, (int) floor((now()->timestamp - $oldestJob) / 60)) : 0, 'schedulerLastRun' => Cache::get('operations.scheduler.last_run')], 'operationsSummary' => $monitor->summary($hotel), 'operationsSettings' => $monitor->settings($hotel), 'backups' => $backups, 'retentionDays' => (int) SecuritySetting::valueOf('audit_retention_days', '365'), 'twoFactorEnabled' => (bool) $request->user()->two_factor_enabled]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:50'], 'severity' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        AuditLogger::record($request, 'audit_exported', 'security', 'sensitive', 'Security audit log exported.');

        return Download::streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Severity', 'Category', 'Action', 'Staff', 'Description', 'Reason', 'IP']);
            $this->filtered($filters)->with('actor')->chunk(500, function ($events) use ($out) {
                foreach ($events as $e) {
                    fputcsv($out, [$e->occurred_at?->toISOString(), $e->severity, $e->category, $e->action, $e->actor?->name, $e->description, $e->reason, $e->ip_address]);
                }
            });
            fclose($out);
        }, 'security-audit-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function settings(Request $request): RedirectResponse
    {
        $v = $request->validate(['retention_days' => ['required', 'integer', Rule::in([30, 90, 180, 365, 730])], 'two_factor_enabled' => ['required', 'boolean'], 'alerts_enabled' => ['required', 'boolean'], 'email_enabled' => ['required', 'boolean'], 'alert_roles' => ['required', 'array', 'min:1'], 'alert_roles.*' => ['string', Rule::in(['super_admin', 'manager'])], 'alert_emails' => ['nullable', 'string', 'max:1000'], 'low_battery_threshold' => ['required', 'integer', 'between:5,50'], 'lock_offline_minutes' => ['required', 'integer', 'between:5,1440'], 'fcm_failure_threshold' => ['required', 'integer', 'between:1,100'], 'alert_cooldown_minutes' => ['required', 'integer', 'between:5,1440']]);
        SecuritySetting::updateOrCreate(['key' => 'audit_retention_days'], ['value' => (string) $v['retention_days']]);
        $request->user()->update(['two_factor_enabled' => $v['two_factor_enabled']]);
        $hotel = $request->user()->hotel;
        $hotelSettings = $hotel->settings ?? [];
        $hotelSettings['operations'] = collect($v)->except(['retention_days', 'two_factor_enabled'])->all();
        $hotel->update(['settings' => $hotelSettings]);
        AuditEvent::where('occurred_at', '<', now()->subDays($v['retention_days']))->delete();
        AuditLogger::record($request, 'security_settings_updated', 'security', 'sensitive', 'Security retention or administrator two-factor settings changed.', $request->user(), null, $v);

        return back()->with('success', 'Security settings updated.');
    }

    public function testAlert(Request $request, OperationsMonitor $monitor): RedirectResponse
    {
        $monitor->inspect($request->user()->hotel, true);
        AuditLogger::record($request, 'operations_alert_tested', 'operations', 'normal', 'A test operations alert was sent.');

        return back()->with('success', 'Test operations alert sent.');
    }

    public function createBackup(Request $request): RedirectResponse
    {
        CreateHotelBackup::dispatch($request->user()->hotel_id);
        AuditLogger::record($request, 'hotel_backup_queued', 'operations', 'sensitive', 'An encrypted property backup was queued.');

        return back()->with('success', 'Encrypted backup queued.');
    }

    public function verifyBackup(Request $request, HotelBackup $backup, HotelBackupManager $manager): RedirectResponse
    {
        try {
            $manager->verify($backup);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
        AuditLogger::record($request, 'hotel_backup_verified', 'operations', 'normal', 'Backup checksum and encrypted manifest were verified.', $backup);

        return back()->with('success', 'Backup is restore-ready.');
    }

    public function retryFailedJob(Request $request, string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLogger::record($request, 'failed_job_retried', 'operations', 'warning', 'A failed background job was queued for retry.', null, null, ['job_uuid' => $uuid]);

        return back()->with('success', 'Failed job queued for retry.');
    }

    public function retryAllFailedJobs(Request $request): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        AuditLogger::record($request, 'all_failed_jobs_retried', 'operations', 'warning', 'All failed background jobs were queued for retry.');

        return back()->with('success', 'All failed jobs queued for retry.');
    }

    public function deleteFailedJob(Request $request, string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->delete(), 404);
        AuditLogger::record($request, 'failed_job_deleted', 'operations', 'sensitive', 'A failed background-job record was deleted.', null, null, ['job_uuid' => $uuid]);

        return back()->with('success', 'Failed job record deleted.');
    }

    private function filtered(array $filters)
    {
        return AuditEvent::query()->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('description', 'like', "%$s%")->orWhere('action', 'like', "%$s%")->orWhere('reason', 'like', "%$s%")->orWhereHas('actor', fn ($u) => $u->where('name', 'like', "%$s%"))))->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))->when($filters['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v))->latest('occurred_at')->latest('id');
    }

    private function data($e): array
    {
        return ['id' => $e->id, 'action' => $e->action, 'category' => $e->category, 'severity' => $e->severity, 'description' => $e->description, 'reason' => $e->reason, 'actor' => $e->actor?->name, 'ipAddress' => $e->ip_address, 'occurredAt' => $e->occurred_at?->toISOString(), 'metadata' => $e->metadata];
    }

    private function failedJobData($job): array
    {
        $payload = json_decode($job->payload, true) ?: [];
        $name = data_get($payload, 'displayName') ?: class_basename((string) data_get($payload, 'data.commandName', 'Background job'));
        $exception = trim(strtok((string) $job->exception, "\n"));

        return ['uuid' => $job->uuid, 'name' => $name, 'queue' => $job->queue, 'connection' => $job->connection, 'error' => mb_substr($exception, 0, 240), 'failedAt' => (string) $job->failed_at];
    }
}
