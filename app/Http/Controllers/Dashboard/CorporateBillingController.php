<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\CorporateInvoiceItem;
use App\Models\CorporateInvoicePayment;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GroupBooking;
use App\Services\Finance\AccountingPeriod;
use App\Services\Folios\FolioLedger;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CorporateBillingController extends Controller
{
    public function index(): Response
    {
        CorporateInvoice::whereIn('status', ['issued', 'partially_paid'])->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
        $invoices = CorporateInvoice::with(['account', 'group'])->latest('issue_date')->get();
        $today = today();
        $aging = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'days_90_plus' => 0];
        foreach ($invoices->whereNotIn('status', ['paid', 'void']) as $invoice) {
            $days = $invoice->due_date->diffInDays($today, false);
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : ($days <= 90 ? 'days_61_90' : 'days_90_plus')));
            $aging[$bucket] += (float) $invoice->balance;
        }
        
        return Inertia::render('Receivables/Index', ['currency' => app('currentHotel')->currency, 'aging' => $aging, 'accounts' => CorporateAccount::withCount('invoices')->orderBy('name')->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'status' => $a->status, 'terms' => $a->payment_terms_days, 'invoiceCount' => $a->invoices_count, 'balance' => (float) $a->invoices()->whereNotIn('status', ['paid', 'void'])->sum('balance')]), 'groups' => GroupBooking::with('corporateAccount')->whereNotNull('corporate_account_id')->whereNotIn('status', ['cancelled'])->get()->map(function ($group) {
            $master = Folio::where('group_booking_id', $group->id)->first();
            $eligible = FolioItem::whereHas('folio.reservation', fn ($q) => $q->where('group_booking_id', $group->id))->where('voided', false)->count();

            return ['id' => $group->id, 'code' => $group->code, 'name' => $group->name, 'account' => $group->corporateAccount?->name, 'eligibleCharges' => $eligible, 'masterFolioId' => $master?->id, 'masterBalance' => (float) ($master?->balance ?? 0), 'uninvoicedCharges' => $master ? $master->items()->where('voided', false)->whereNotIn('id', CorporateInvoiceItem::whereNotNull('folio_item_id')->pluck('folio_item_id'))->count() : 0];
        }), 'invoices' => $invoices->map(fn ($i) => $this->data($i))]);
    }

    public function show(CorporateInvoice $invoice): Response
    {
        $invoice->load(['account', 'group', 'items.reservation.guest', 'payments']);

        return Inertia::render('Receivables/Show', [
            'invoice' => $this->data($invoice) + [
                'accountCode' => $invoice->account?->code,
                'billingAddress' => $invoice->account?->billing_address,
                'taxNumber' => $invoice->account?->tax_number,
                'subtotal' => (float) $invoice->subtotal,
                'tax' => (float) $invoice->tax_total,
                'voidReason' => $invoice->void_reason,
                'items' => $invoice->items->map(fn ($item) => ['id' => $item->id, 'description' => $item->description, 'guest' => $item->reservation?->guest ? trim($item->reservation->guest->first_name.' '.$item->reservation->guest->last_name) : null, 'amount' => (float) $item->amount, 'tax' => (float) $item->tax_amount, 'total' => (float) $item->total_amount]),
                'payments' => $invoice->payments->sortByDesc('processed_at')->values()->map(fn ($payment) => ['id' => $payment->id, 'type' => $payment->type, 'method' => $payment->method, 'amount' => (float) $payment->amount, 'reference' => $payment->reference, 'notes' => $payment->notes, 'processedAt' => $payment->processed_at->toIso8601String()]),
            ],
        ]);
    }

    public function master(GroupBooking $group): RedirectResponse
    {
        $folio = Folio::forGroup($group);

        return redirect()->route('dashboard.folios.show', $folio);
    }

    public function transfer(Request $r, GroupBooking $group, FolioLedger $ledger): RedirectResponse
    {
        $ids = $r->validate(['item_ids' => ['nullable', 'array'], 'item_ids.*' => ['integer', 'exists:folio_items,id']])['item_ids'] ?? FolioItem::whereHas('folio.reservation', fn ($q) => $q->where('group_booking_id', $group->id))->where('voided', false)->pluck('id')->all();
        abort_if(empty($ids), 422, 'There are no eligible member charges to transfer.');
        $master = Folio::forGroup($group);
        $items = FolioItem::with('folio.reservation')->whereIn('id', $ids)->where('voided', false)->get();
        abort_unless($items->count() === count(array_unique($ids)), 422, 'One or more charges cannot be transferred.');
        abort_if($items->contains(fn ($item) => $item->folio?->reservation?->group_booking_id !== $group->id), 422, 'Every charge must belong to this group.');
        DB::transaction(function () use ($items, $master, $r, $ledger) {
            foreach ($items as $item) {
                $copy = FolioItem::firstOrCreate(['idempotency_key' => "group-transfer:{$item->id}"], ['folio_id' => $master->id, 'type' => $item->type, 'description' => $item->description, 'quantity' => $item->quantity, 'unit_amount' => $item->unit_amount, 'tax_amount' => $item->tax_amount, 'total_amount' => $item->total_amount, 'service_date' => $item->service_date, 'posted_by' => $r->user()->id, 'metadata' => ['source_folio_item_id' => $item->id, 'source_reservation_id' => $item->folio->reservation_id]]);
                $item->update(['voided' => true, 'void_reason' => "Transferred to group master folio {$master->number}", 'voided_at' => now(), 'voided_by' => $r->user()->id, 'transferred_to_folio_id' => $master->id, 'transferred_at' => now(), 'transferred_by' => $r->user()->id]);
                $ledger->recalculate($item->folio);
            }$ledger->recalculate($master);
        });
        AuditLogger::record($r, 'group_charges_transferred', 'finance', 'critical', count($ids)." charge(s) transferred to {$master->number}.", $master);

        return back()->with('success', 'Selected charges moved to the group master folio.');
    }

    public function issue(Request $r, GroupBooking $group): RedirectResponse
    {
        $v = $r->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $account = $group->corporateAccount;
        abort_unless($account && $account->status === 'active', 422, 'An active corporate account is required.');
        $folio = Folio::forGroup($group);
        $items = $folio->items()->where('voided', false)->whereNotIn('id', CorporateInvoiceItem::whereNotNull('folio_item_id')->pluck('folio_item_id'))->get();
        abort_if($items->isEmpty(), 422, 'The master folio has no uninvoiced charges.');
        $invoice = DB::transaction(function () use ($r, $group, $account, $folio, $items, $v) {
            $subtotal = (float) $items->sum(fn ($item) => (float) $item->total_amount - (float) $item->tax_amount);
            $tax = (float) $items->sum('tax_amount');
            $total = (float) $items->sum('total_amount');
            $invoice = CorporateInvoice::create(['corporate_account_id' => $account->id, 'group_booking_id' => $group->id, 'folio_id' => $folio->id, 'number' => CorporateInvoice::nextNumber(), 'status' => 'issued', 'currency' => $folio->currency, 'issue_date' => today(), 'due_date' => today()->addDays($account->payment_terms_days), 'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total, 'balance' => $total, 'notes' => $v['notes'] ?? $group->billing_instructions, 'issued_by' => $r->user()->id, 'issued_at' => now()]);
            foreach ($items as $item) {
                CorporateInvoiceItem::create(['corporate_invoice_id' => $invoice->id, 'folio_item_id' => $item->id, 'reservation_id' => data_get($item->metadata, 'source_reservation_id'), 'description' => $item->description, 'amount' => (float) $item->total_amount - (float) $item->tax_amount, 'tax_amount' => $item->tax_amount, 'total_amount' => $item->total_amount, 'metadata' => $item->metadata]);
            }

            return $invoice;
        });
        AuditLogger::record($r, 'corporate_invoice_issued', 'finance', 'critical', "Invoice {$invoice->number} issued.", $invoice);

        return redirect()->route('dashboard.receivables.index')->with('success', 'Corporate invoice issued.');
    }

    public function payment(Request $r, CorporateInvoice $invoice, AccountingPeriod $period): RedirectResponse
    {
        abort_if(in_array($invoice->status, ['paid', 'void']), 422, 'This invoice does not accept payments.');
        $period->ensureOpen(today());
        $v = $r->validate(['amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'external_terminal', 'provider', 'other'])], 'reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:1000']]);
        abort_if((float) $v['amount'] > (float) $invoice->balance, 422, 'Payment exceeds the invoice balance.');
        CorporateInvoicePayment::create([...$v, 'corporate_invoice_id' => $invoice->id, 'type' => 'payment', 'processed_at' => now(), 'recorded_by' => $r->user()->id]);
        $this->recalculate($invoice);
        AuditLogger::record($r, 'corporate_payment_recorded', 'finance', 'sensitive', "Payment recorded on {$invoice->number}.", $invoice, null, ['amount' => (float) $v['amount']]);

        return back()->with('success', 'Corporate payment allocated.');
    }

    public function credit(Request $r, CorporateInvoice $invoice, AccountingPeriod $period): RedirectResponse
    {
        abort_if(in_array($invoice->status, ['paid', 'void']), 422, 'This invoice does not accept credits.');
        $period->ensureOpen(today());
        $v = $r->validate(['amount' => ['required', 'numeric', 'gt:0'], 'notes' => ['required', 'string', 'min:5', 'max:1000']]);
        abort_if((float) $v['amount'] > (float) $invoice->balance, 422, 'Credit exceeds the invoice balance.');
        CorporateInvoicePayment::create(['corporate_invoice_id' => $invoice->id, 'type' => 'credit', 'method' => 'credit_note', 'amount' => $v['amount'], 'notes' => $v['notes'], 'processed_at' => now(), 'recorded_by' => $r->user()->id]);
        $this->recalculate($invoice);
        AuditLogger::record($r, 'corporate_credit_issued', 'finance', 'critical', "Credit note issued on {$invoice->number}.", $invoice, $v['notes'], ['amount' => (float) $v['amount']]);

        return back()->with('success', 'Credit note applied.');
    }

    public function void(Request $r, CorporateInvoice $invoice): RedirectResponse
    {
        $reason = $r->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']])['reason'];
        abort_if((float) $invoice->paid_total > 0, 422, 'An invoice with payments cannot be voided.');
        $invoice->update(['status' => 'void', 'balance' => 0, 'voided_by' => $r->user()->id, 'voided_at' => now(), 'void_reason' => $reason]);
        AuditLogger::record($r, 'corporate_invoice_voided', 'finance', 'critical', "Invoice {$invoice->number} voided.", $invoice, $reason);

        return back()->with('success', 'Invoice voided.');
    }

    public function statement(CorporateAccount $account): HttpResponse
    {
        $rows = $account->invoices()->orderBy('issue_date')->get();
        $csv = "Invoice,Issue date,Due date,Status,Total,Paid,Balance\n";
        foreach ($rows as $i) {
            $csv .= collect([$i->number, $i->issue_date->toDateString(), $i->due_date->toDateString(), $i->status, $i->total, $i->paid_total, $i->balance])->map(fn ($x) => '"'.str_replace('"', '""', (string) $x).'"')->implode(',')."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$account->code.'-statement.csv"']);
    }

    private function recalculate(CorporateInvoice $invoice): void
    {
        $payments = (float) $invoice->payments()->where('type', 'payment')->sum('amount');
        $credits = (float) $invoice->payments()->where('type', 'credit')->sum('amount');
        $balance = max(0, round((float) $invoice->total - $payments - $credits, 2));
        $invoice->update(['paid_total' => $payments, 'balance' => $balance, 'status' => $balance <= 0 ? 'paid' : ($payments + $credits > 0 ? 'partially_paid' : ($invoice->due_date->isPast() ? 'overdue' : 'issued'))]);
    }

    private function data(CorporateInvoice $i): array
    {
        return ['id' => $i->id, 'number' => $i->number, 'account' => $i->account?->name, 'accountId' => $i->corporate_account_id, 'group' => $i->group?->name, 'status' => $i->status, 'currency' => $i->currency, 'issueDate' => $i->issue_date->toDateString(), 'dueDate' => $i->due_date->toDateString(), 'total' => (float) $i->total, 'paid' => (float) $i->paid_total, 'balance' => (float) $i->balance, 'notes' => $i->notes];
    }
}
