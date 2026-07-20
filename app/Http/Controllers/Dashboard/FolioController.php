<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioPayment;
use App\Models\Reservation;
use App\Services\Finance\AccountingPeriod;
use App\Services\Folios\FolioLedger;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function index(Request $r): Response
    {
        $status = $r->input('status', 'open');
        $folios = Folio::with(['guest', 'reservation', 'groupBooking'])->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest()->get();

        return Inertia::render('Folios/Index', ['status' => $status, 'folios' => $folios->map(fn ($f) => ['id' => $f->id, 'number' => $f->number, 'reservationId' => $f->reservation_id, 'reference' => $f->reservation?->reference ?? $f->groupBooking?->code, 'guestName' => $f->guest ? trim($f->guest->first_name.' '.$f->guest->last_name) : ($f->groupBooking?->name ?? 'Group master'), 'currency' => $f->currency, 'status' => $f->status, 'charges' => (float) $f->charges_total, 'payments' => (float) $f->payments_total, 'refunds' => (float) $f->refunds_total, 'balance' => (float) $f->balance, 'updatedAt' => $f->updated_at?->toISOString()])]);
    }

    public function reservation(Reservation $reservation): RedirectResponse
    {
        $folio = Folio::forReservation($reservation);
        $folio->update(['checkin_id' => $reservation->guest->checkins()->where('reservation_id', $reservation->id)->latest()->value('id')]);

        return redirect()->route('dashboard.folios.show', $folio);
    }

    public function show(Folio $folio): Response
    {
        $folio->load(['guest', 'reservation', 'groupBooking', 'items', 'payments']);

        return Inertia::render('Folios/Show', ['folio' => ['id' => $folio->id, 'number' => $folio->number, 'reservationId' => $folio->reservation_id, 'reference' => $folio->reservation?->reference ?? $folio->groupBooking?->code, 'guestName' => $folio->guest ? trim($folio->guest->first_name.' '.$folio->guest->last_name) : ($folio->groupBooking?->name ?? 'Group master'), 'currency' => $folio->currency, 'status' => $folio->status, 'charges' => (float) $folio->charges_total, 'payments' => (float) $folio->payments_total, 'refunds' => (float) $folio->refunds_total, 'balance' => (float) $folio->balance], 'items' => $folio->items->sortByDesc('service_date')->values()->map(fn ($i) => ['id' => $i->id, 'type' => $i->type, 'description' => $i->description, 'quantity' => (float) $i->quantity, 'unitAmount' => (float) $i->unit_amount, 'taxAmount' => (float) $i->tax_amount, 'totalAmount' => (float) $i->total_amount, 'serviceDate' => $i->service_date->toDateString(), 'voided' => $i->voided, 'voidReason' => $i->void_reason]), 'payments' => $folio->payments->sortByDesc('processed_at')->values()->map(fn ($p) => ['id' => $p->id, 'parentPaymentId' => $p->parent_payment_id, 'type' => $p->type, 'method' => $p->method, 'provider' => $p->provider, 'externalReference' => $p->external_reference, 'amount' => (float) $p->amount, 'status' => $p->status, 'notes' => $p->notes, 'processedAt' => $p->processed_at->toISOString()])]);
    }

    public function item(Request $r, Folio $folio, FolioLedger $ledger, AccountingPeriod $period): RedirectResponse
    {
        $v = $r->validate(['type' => ['required', Rule::in(['room_charge', 'tax', 'deposit', 'service', 'damage', 'no_show', 'cancellation', 'adjustment'])], 'description' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'gt:0'], 'unit_amount' => ['required', 'numeric'], 'tax_amount' => ['required', 'numeric', 'min:0'], 'service_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $period->ensureOpen($v['service_date']);
        if ($v['type'] === 'adjustment') {
            abort_unless($r->user()->hasPermission('stays.force_departure'), 403);
        }$total = round((float) $v['quantity'] * (float) $v['unit_amount'] + (float) $v['tax_amount'], 2);
        FolioItem::create([...$v, 'folio_id' => $folio->id, 'total_amount' => $total, 'posted_by' => $r->user()->id, 'metadata' => filled($v['notes'] ?? null) ? ['notes' => $v['notes']] : null]);
        $ledger->recalculate($folio);
        AuditLogger::record($r, 'folio_charge_posted', 'finance', 'sensitive', "Charge posted to {$folio->number}.", $folio, null, ['type' => $v['type'], 'amount' => $total]);

        return back()->with('success', 'Folio charge posted.');
    }

    public function payment(Request $r, Folio $folio, FolioLedger $ledger, AccountingPeriod $period): RedirectResponse
    {
        $v = $r->validate(['method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'external_terminal', 'provider', 'other'])], 'amount' => ['required', 'numeric', 'gt:0'], 'provider' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:150'], 'idempotency_key' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $v['idempotency_key'] = $v['idempotency_key'] ?? $r->header('Idempotency-Key') ?? (filled($v['external_reference'] ?? null) ? hash('sha256', "payment:{$folio->id}:{$v['provider']}:{$v['external_reference']}") : (string) \Illuminate\Support\Str::uuid());
        $period->ensureOpen(today());
        $payment = FolioPayment::firstOrCreate(['idempotency_key' => $v['idempotency_key']], [...$v, 'folio_id' => $folio->id, 'type' => 'payment', 'status' => 'completed', 'processed_at' => now(), 'recorded_by' => $r->user()->id]);
        abort_if(! $payment->wasRecentlyCreated && ($payment->folio_id !== $folio->id || $payment->type !== 'payment' || (float) $payment->amount !== (float) $v['amount']), 409, 'This request key was already used for a different payment.');
        $ledger->recalculate($folio);
        AuditLogger::record($r, 'folio_payment_recorded', 'finance', 'sensitive', "Payment recorded on {$folio->number}.", $folio, null, ['method' => $v['method'], 'amount' => (float) $v['amount']]);

        return back()->with('success', 'Payment recorded.');
    }

    public function refund(Request $r, Folio $folio, FolioLedger $ledger, AccountingPeriod $period): RedirectResponse
    {
        $v = $r->validate(['parent_payment_id' => ['nullable', 'exists:folio_payments,id'], 'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'external_terminal', 'provider', 'other'])], 'amount' => ['required', 'numeric', 'gt:0'], 'provider' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:150'], 'idempotency_key' => ['nullable', 'string', 'max:100'], 'notes' => ['required', 'string', 'max:1000']]);
        $v['idempotency_key'] = $v['idempotency_key'] ?? $r->header('Idempotency-Key') ?? (filled($v['external_reference'] ?? null) ? hash('sha256', "refund:{$folio->id}:{$v['provider']}:{$v['external_reference']}") : (string) \Illuminate\Support\Str::uuid());
        $period->ensureOpen(today());
        abort_if((float) $v['amount'] > ((float) $folio->payments()->where('type', 'payment')->where('status', 'completed')->sum('amount') - (float) $folio->payments()->where('type', 'refund')->where('status', 'completed')->sum('amount')), 422, 'Refund exceeds the net payments collected.');
        if ($v['parent_payment_id'] ?? null) {
            abort_unless($folio->payments()->whereKey($v['parent_payment_id'])->exists(), 422, 'The selected payment does not belong to this folio.');
        }$payment = FolioPayment::firstOrCreate(['idempotency_key' => $v['idempotency_key']], [...$v, 'folio_id' => $folio->id, 'type' => 'refund', 'status' => 'completed', 'processed_at' => now(), 'recorded_by' => $r->user()->id]);
        abort_if(! $payment->wasRecentlyCreated && ($payment->folio_id !== $folio->id || $payment->type !== 'refund' || (float) $payment->amount !== (float) $v['amount']), 409, 'This request key was already used for a different refund.');
        $ledger->recalculate($folio);
        AuditLogger::record($r, 'folio_refund_recorded', 'finance', 'critical', "Refund recorded on {$folio->number}.", $folio, $v['notes'], ['amount' => (float) $v['amount']]);

        return back()->with('success', 'Refund recorded.');
    }

    public function void(Request $r, FolioItem $item, FolioLedger $ledger): RedirectResponse
    {
        $reason = $r->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        abort_if($item->voided, 422, 'Charge is already voided.');
        $item->update(['voided' => true, 'void_reason' => $reason, 'voided_at' => now(), 'voided_by' => $r->user()->id]);
        $ledger->recalculate($item->folio);
        AuditLogger::record($r, 'folio_charge_voided', 'finance', 'critical', 'Folio charge voided.', $item, $reason, ['amount' => (float) $item->total_amount]);

        return back()->with('success', 'Charge voided.');
    }

    public function reconciliation(): HttpResponse
    {
        $rows = FolioPayment::with(['folio.guest'])->orderByDesc('processed_at')->get();
        $csv = "Date,Folio,Guest,Type,Method,Provider,Reference,Amount,Status\n";
        foreach ($rows as $p) {
            $csv .= collect([$p->processed_at?->toDateTimeString(), $p->folio?->number, trim(($p->folio?->guest?->first_name ?? '').' '.($p->folio?->guest?->last_name ?? '')), $p->type, $p->method, $p->provider, $p->external_reference, $p->amount, $p->status])->map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"')->implode(',')."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="folio-reconciliation.csv"']);
    }
}
