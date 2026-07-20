<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NightAudit;
use App\Services\Finance\AccountingExporter;
use App\Services\NightAudit\NightAuditService;
use App\Services\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NightAuditController extends Controller
{
    public function index(Request $request, NightAuditService $service): Response
    {
        $hotel = app('currentHotel');
        $date = Carbon::parse($request->input('date', $service->suggestedDate($hotel)), $hotel->timezone)->startOfDay();
        $existing = NightAudit::whereDate('business_date', $date)->first();

        return Inertia::render('NightAudit/Index', [
            'businessDate' => $date->toDateString(),
            'timezone' => $hotel->timezone,
            'currency' => $hotel->currency,
            'preview' => $service->preview($date),
            'existing' => $existing ? $this->auditData($existing->load(['closer', 'reopener'])) : null,
            'history' => NightAudit::with(['closer', 'reopener'])->latest('business_date')->limit(30)->get()->map(fn ($audit) => $this->auditData($audit)),
        ]);
    }

    public function close(Request $request, NightAuditService $service): RedirectResponse
    {
        $validated = $request->validate(['business_date' => ['required', 'date'], 'override_reason' => ['nullable', 'string', 'min:8', 'max:1000']]);
        $hotel = app('currentHotel');
        $date = Carbon::parse($validated['business_date'], $hotel->timezone)->startOfDay();
        abort_if($date->isFuture(), 422, 'A future business date cannot be closed.');
        $audit = $service->close($hotel, $date, $request->user()->id, $validated['override_reason'] ?? null);
        AuditLogger::record($request, 'night_audit_closed', 'finance', 'critical', "Business date {$date->toDateString()} closed.", $audit, $validated['override_reason'] ?? null);

        return back()->with('success', 'Night audit completed and the business date was closed.');
    }

    public function reopen(Request $request, NightAudit $audit, NightAuditService $service, AccountingExporter $exporter): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']])['reason'];
        \Illuminate\Support\Facades\DB::transaction(function () use ($audit, $service, $exporter, $request, $reason) {
            $service->reopen($audit, $request->user()->id, $reason);
            $postedBatch = \App\Models\AccountingExportBatch::where('night_audit_id', $audit->id)->whereNull('reversal_of_id')->where('status', 'posted')->first();
            if ($postedBatch) {
                $exporter->reverse($postedBatch, $request->user()->id);
            }
        });
        AuditLogger::record($request, 'night_audit_reopened', 'finance', 'critical', "Business date {$audit->business_date->toDateString()} reopened.", $audit, $reason);

        return back()->with('success', 'The latest business date has been reopened.');
    }

    public function export(NightAudit $audit): HttpResponse
    {
        $exceptions = collect($audit->exceptions['blockers'] ?? [])->merge($audit->exceptions['warnings'] ?? []);
        $csv = "Night Audit,{$audit->business_date->toDateString()}\nMetric,Value\nStatus,{$audit->status}\nRoom revenue,{$audit->room_revenue}\nPayments,{$audit->payments}\nRefunds,{$audit->refunds}\nOutstanding balance,{$audit->outstanding_balance}\nOccupied rooms,{$audit->occupied_rooms}\nArrivals,{$audit->arrivals}\nDepartures,{$audit->departures}\nNo shows,{$audit->no_shows}\nCharges posted,{$audit->charges_posted}\n\nException type,Reference,Detail\n";
        foreach ($exceptions as $exception) {
            $csv .= collect([$exception['type'] ?? '', $exception['label'] ?? '', $exception['detail'] ?? ''])->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(',')."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="night-audit-'.$audit->business_date->toDateString().'.csv"']);
    }

    private function auditData(NightAudit $audit): array
    {
        return ['id' => $audit->id, 'businessDate' => $audit->business_date->toDateString(), 'status' => $audit->status, 'chargesPosted' => $audit->charges_posted, 'roomRevenue' => (float) $audit->room_revenue, 'payments' => (float) $audit->payments, 'refunds' => (float) $audit->refunds, 'outstandingBalance' => (float) $audit->outstanding_balance, 'occupiedRooms' => $audit->occupied_rooms, 'arrivals' => $audit->arrivals, 'departures' => $audit->departures, 'noShows' => $audit->no_shows, 'exceptions' => $audit->exceptions, 'overrideReason' => $audit->override_reason, 'closedBy' => $audit->closer?->name, 'closedAt' => $audit->closed_at?->toISOString(), 'reopenedBy' => $audit->reopener?->name, 'reopenedAt' => $audit->reopened_at?->toISOString(), 'reopenReason' => $audit->reopen_reason];
    }
}
