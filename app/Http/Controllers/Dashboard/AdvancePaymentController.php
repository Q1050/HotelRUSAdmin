<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdvancePayment;
use App\Models\AdvancePaymentAllocation;
use App\Models\AdvancePaymentEvent;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\CorporateInvoicePayment;
use App\Models\Folio;
use App\Models\FolioPayment;
use App\Models\GroupBooking;
use App\Models\Guest;
use App\Models\Reservation;
use App\Services\Finance\AccountingPeriod;
use App\Services\Folios\FolioLedger;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdvancePaymentController extends Controller
{
    public function index(): Response
    {
        $payments = AdvancePayment::with(['guest', 'corporateAccount', 'reservation', 'groupBooking'])->latest('received_at')->get();

        return Inertia::render('Deposits/Index', [
            'currency' => app('currentHotel')->currency,
            'summary' => ['collected' => (float) $payments->sum('amount'), 'available' => (float) $payments->sum('available_balance'), 'allocated' => (float) $payments->sum('allocated_total'), 'refunded' => (float) $payments->sum('refunded_total'), 'forfeited' => (float) $payments->sum('forfeited_total')],
            'payments' => $payments->map(fn ($payment) => $this->data($payment)),
            'guests' => Guest::where('account_status', '!=', 'merged')->orderBy('first_name')->get()->map(fn ($guest) => ['id' => $guest->id, 'name' => trim($guest->first_name.' '.$guest->last_name)]),
            'accounts' => CorporateAccount::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'reservations' => Reservation::with('guest')->whereNotIn('status', ['cancelled', 'no_show', 'completed'])->latest('arrival_date')->get()->map(fn ($reservation) => ['id' => $reservation->id, 'reference' => $reservation->reference, 'guestId' => $reservation->guest_id, 'guestName' => trim($reservation->guest->first_name.' '.$reservation->guest->last_name), 'required' => (float) data_get($reservation->pricing_snapshot, 'deposit_required', 0)]),
            'groups' => GroupBooking::whereNotIn('status', ['cancelled', 'closed'])->orderBy('name')->get(['id', 'name', 'code', 'corporate_account_id']),
            'folios' => Folio::with(['guest', 'groupBooking'])->where('status', 'open')->orderByDesc('updated_at')->get()->map(fn ($folio) => ['id' => $folio->id, 'number' => $folio->number, 'owner' => $folio->guest ? trim($folio->guest->first_name.' '.$folio->guest->last_name) : ($folio->groupBooking?->name ?? 'Group master'), 'guestId' => $folio->guest_id, 'groupId' => $folio->group_booking_id, 'balance' => (float) $folio->balance]),
            'invoices' => CorporateInvoice::with('account')->whereNotIn('status', ['paid', 'void'])->orderBy('due_date')->get()->map(fn ($invoice) => ['id' => $invoice->id, 'number' => $invoice->number, 'accountId' => $invoice->corporate_account_id, 'account' => $invoice->account?->name, 'balance' => (float) $invoice->balance]),
        ]);
    }

    public function store(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $period->ensureOpen(today());
        $data = $request->validate(['owner_type' => ['required', Rule::in(['guest', 'corporate'])], 'guest_id' => ['nullable', 'required_if:owner_type,guest', 'exists:guests,id'], 'corporate_account_id' => ['nullable', 'required_if:owner_type,corporate', 'exists:corporate_accounts,id'], 'reservation_id' => ['nullable', 'exists:reservations,id'], 'group_booking_id' => ['nullable', 'exists:group_bookings,id'], 'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'external_terminal', 'provider', 'other'])], 'provider' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:150'], 'amount' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        if ($data['owner_type'] === 'guest') {
            abort_if(filled($data['corporate_account_id'] ?? null) || filled($data['group_booking_id'] ?? null), 422, 'Guest deposits cannot be assigned to a company or group.');
            if ($data['reservation_id'] ?? null) {
                abort_unless(Reservation::whereKey($data['reservation_id'])->where('guest_id', $data['guest_id'])->exists(), 422, 'Reservation does not belong to this guest.');
            }
        } else {
            abort_if(filled($data['guest_id'] ?? null) || filled($data['reservation_id'] ?? null), 422, 'Corporate deposits cannot be assigned to a guest reservation.');
            if ($data['group_booking_id'] ?? null) {
                abort_unless(GroupBooking::whereKey($data['group_booking_id'])->where('corporate_account_id', $data['corporate_account_id'])->exists(), 422, 'Group does not belong to this company.');
            }
        }
        unset($data['owner_type']);
        $payment = AdvancePayment::create([...$data, 'receipt_number' => AdvancePayment::nextReceiptNumber(), 'available_balance' => $data['amount'], 'received_at' => now(), 'recorded_by' => $request->user()->id]);
        AdvancePaymentEvent::create(['advance_payment_id' => $payment->id, 'type' => 'received', 'amount' => $payment->amount, 'occurred_at' => $payment->received_at, 'recorded_by' => $request->user()->id]);
        AuditLogger::record($request, 'advance_payment_received', 'finance', 'sensitive', "Advance payment {$payment->receipt_number} received.", $payment, null, ['amount' => (float) $payment->amount, 'method' => $payment->method]);

        return back()->with('success', 'Advance payment recorded and receipt created.');
    }

    public function allocate(Request $request, AdvancePayment $payment, FolioLedger $ledger): RedirectResponse
    {
        abort_if((float) $payment->available_balance <= 0, 422, 'This advance payment has no available balance.');
        $data = $request->validate(['target_type' => ['required', Rule::in(['folio', 'invoice'])], 'folio_id' => ['nullable', 'required_if:target_type,folio', 'exists:folios,id'], 'corporate_invoice_id' => ['nullable', 'required_if:target_type,invoice', 'exists:corporate_invoices,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        abort_if((float) $data['amount'] > (float) $payment->available_balance, 422, 'Allocation exceeds the available deposit balance.');
        $folio = $data['target_type'] === 'folio' ? Folio::findOrFail($data['folio_id']) : null;
        $invoice = $data['target_type'] === 'invoice' ? CorporateInvoice::findOrFail($data['corporate_invoice_id']) : null;
        $this->ensureOwnerMatches($payment, $folio, $invoice);
        abort_if($folio && (float) $data['amount'] > max(0, (float) $folio->balance), 422, 'Allocation exceeds the folio balance.');
        abort_if($invoice && (float) $data['amount'] > (float) $invoice->balance, 422, 'Allocation exceeds the invoice balance.');

        DB::transaction(function () use ($payment, $folio, $invoice, $data, $request, $ledger) {
            $folioPayment = $folio ? FolioPayment::create(['folio_id' => $folio->id, 'type' => 'payment', 'method' => 'advance_deposit', 'provider' => $payment->provider, 'external_reference' => $payment->receipt_number, 'amount' => $data['amount'], 'status' => 'completed', 'notes' => $data['notes'] ?? null, 'processed_at' => now(), 'recorded_by' => $request->user()->id]) : null;
            $invoicePayment = $invoice ? CorporateInvoicePayment::create(['corporate_invoice_id' => $invoice->id, 'type' => 'payment', 'method' => 'advance_deposit', 'reference' => $payment->receipt_number, 'amount' => $data['amount'], 'notes' => $data['notes'] ?? null, 'processed_at' => now(), 'recorded_by' => $request->user()->id]) : null;
            AdvancePaymentAllocation::create(['advance_payment_id' => $payment->id, 'folio_id' => $folio?->id, 'corporate_invoice_id' => $invoice?->id, 'folio_payment_id' => $folioPayment?->id, 'corporate_invoice_payment_id' => $invoicePayment?->id, 'amount' => $data['amount'], 'notes' => $data['notes'] ?? null, 'allocated_at' => now(), 'allocated_by' => $request->user()->id]);
            $payment->increment('allocated_total', $data['amount']);
            $payment->refreshTotals();
            if ($folio) {
                $ledger->recalculate($folio);
            }
            if ($invoice) {
                $this->recalculateInvoice($invoice);
            }
        });
        AuditLogger::record($request, 'advance_payment_allocated', 'finance', 'sensitive', "Funds from {$payment->receipt_number} allocated.", $payment, null, ['amount' => (float) $data['amount'], 'target' => $data['target_type']]);

        return back()->with('success', 'Advance funds allocated.');
    }

    public function refund(Request $request, AdvancePayment $payment, AccountingPeriod $period): RedirectResponse
    {
        $period->ensureOpen(today());

        return $this->closeFunds($request, $payment, 'refunded_total', 'advance_payment_refunded', 'Deposit refund recorded.');
    }

    public function forfeit(Request $request, AdvancePayment $payment, AccountingPeriod $period): RedirectResponse
    {
        $period->ensureOpen(today());

        return $this->closeFunds($request, $payment, 'forfeited_total', 'advance_payment_forfeited', 'Deposit forfeiture recorded.');
    }

    public function receipt(AdvancePayment $payment): Response
    {
        $payment->load(['guest', 'corporateAccount', 'reservation', 'groupBooking', 'allocations.folio', 'allocations.corporateInvoice']);

        return Inertia::render('Deposits/Receipt', ['currency' => app('currentHotel')->currency, 'payment' => $this->data($payment) + ['method' => $payment->method, 'provider' => $payment->provider, 'reference' => $payment->external_reference, 'notes' => $payment->notes, 'allocations' => $payment->allocations->map(fn ($allocation) => ['id' => $allocation->id, 'target' => $allocation->folio?->number ?? $allocation->corporateInvoice?->number, 'amount' => (float) $allocation->amount, 'allocatedAt' => $allocation->allocated_at->toIso8601String()])]]);
    }

    private function closeFunds(Request $request, AdvancePayment $payment, string $column, string $event, string $message): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        abort_if((float) $data['amount'] > (float) $payment->available_balance, 422, 'Amount exceeds the available deposit balance.');
        $payment->increment($column, $data['amount']);
        $payment->refreshTotals();
        AdvancePaymentEvent::create(['advance_payment_id' => $payment->id, 'type' => $column === 'refunded_total' ? 'refund' : 'forfeit', 'amount' => $data['amount'], 'reason' => $data['reason'], 'occurred_at' => now(), 'recorded_by' => $request->user()->id]);
        AuditLogger::record($request, $event, 'finance', 'critical', $message, $payment, $data['reason'], ['amount' => (float) $data['amount']]);

        return back()->with('success', $message);
    }

    private function ensureOwnerMatches(AdvancePayment $payment, ?Folio $folio, ?CorporateInvoice $invoice): void
    {
        if ($payment->guest_id) {
            abort_unless($folio && $folio->guest_id === $payment->guest_id, 422, 'This guest deposit can only pay that guest’s folio.');
        }
        if ($payment->corporate_account_id && $invoice) {
            abort_unless($invoice->corporate_account_id === $payment->corporate_account_id, 422, 'Invoice belongs to another corporate account.');
        }
        if ($payment->corporate_account_id && $folio) {
            abort_unless($folio->groupBooking?->corporate_account_id === $payment->corporate_account_id, 422, 'Folio belongs to another corporate account.');
        }
    }

    private function recalculateInvoice(CorporateInvoice $invoice): void
    {
        $payments = (float) $invoice->payments()->where('type', 'payment')->sum('amount');
        $credits = (float) $invoice->payments()->where('type', 'credit')->sum('amount');
        $balance = max(0, round((float) $invoice->total - $payments - $credits, 2));
        $invoice->update(['paid_total' => $payments, 'balance' => $balance, 'status' => $balance <= 0 ? 'paid' : ($payments + $credits > 0 ? 'partially_paid' : ($invoice->due_date->isPast() ? 'overdue' : 'issued'))]);
    }

    private function data(AdvancePayment $payment): array
    {
        return ['id' => $payment->id, 'receipt' => $payment->receipt_number, 'owner' => $payment->guest ? trim($payment->guest->first_name.' '.$payment->guest->last_name) : $payment->corporateAccount?->name, 'ownerType' => $payment->guest_id ? 'guest' : 'corporate', 'reservation' => $payment->reservation?->reference, 'group' => $payment->groupBooking?->name, 'amount' => (float) $payment->amount, 'allocated' => (float) $payment->allocated_total, 'refunded' => (float) $payment->refunded_total, 'forfeited' => (float) $payment->forfeited_total, 'available' => (float) $payment->available_balance, 'status' => $payment->status, 'receivedAt' => $payment->received_at->toIso8601String()];
    }
}
