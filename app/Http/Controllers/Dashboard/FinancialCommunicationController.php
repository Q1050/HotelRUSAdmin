<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverFinancialCommunication;
use App\Models\AdvancePayment;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\FinancialCommunication;
use App\Services\Finance\FinancialCommunicator;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialCommunicationController extends Controller
{
    public function index(FinancialCommunicator $communicator): Response
    {
        return Inertia::render('Communications/Index', [
            'settings' => $communicator->settings(app('currentHotel')),
            'deliveries' => FinancialCommunication::latest()->limit(100)->get()->map(fn ($delivery) => ['id' => $delivery->id, 'type' => $delivery->document_type, 'recipient' => $delivery->recipient, 'subject' => $delivery->subject, 'status' => $delivery->status, 'attempts' => $delivery->attempts, 'scheduledFor' => $delivery->scheduled_for?->toIso8601String(), 'sentAt' => $delivery->sent_at?->toIso8601String(), 'openedAt' => $delivery->opened_at?->toIso8601String(), 'error' => $delivery->last_error]),
            'invoices' => CorporateInvoice::with('account')->whereNotIn('status', ['void'])->latest()->get()->map(fn ($invoice) => ['id' => $invoice->id, 'number' => $invoice->number, 'recipient' => $invoice->account?->email, 'account' => $invoice->account?->name, 'balance' => (float) $invoice->balance]),
            'accounts' => CorporateAccount::whereNotNull('email')->orderBy('name')->get(['id', 'name', 'code', 'email']),
            'deposits' => AdvancePayment::with(['guest', 'corporateAccount'])->latest()->get()->map(fn ($payment) => ['id' => $payment->id, 'number' => $payment->receipt_number, 'owner' => $payment->guest ? trim($payment->guest->first_name.' '.$payment->guest->last_name) : $payment->corporateAccount?->name, 'recipient' => $payment->guest?->email ?? $payment->corporateAccount?->email, 'amount' => (float) $payment->amount]),
        ]);
    }

    public function settings(Request $request, FinancialCommunicator $communicator): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'reply_to' => ['nullable', 'email', 'max:150'], 'due_days_before' => ['required', 'integer', 'between:0,30'], 'overdue_repeat_days' => ['required', 'integer', 'between:1,90'], 'invoice_subject' => ['required', 'string', 'max:200'], 'invoice_body' => ['required', 'string', 'max:5000'], 'statement_subject' => ['required', 'string', 'max:200'], 'statement_body' => ['required', 'string', 'max:5000'], 'deposit_subject' => ['required', 'string', 'max:200'], 'deposit_body' => ['required', 'string', 'max:5000'], 'reminder_subject' => ['required', 'string', 'max:200'], 'reminder_body' => ['required', 'string', 'max:5000']]);
        $communicator->updateSettings(app('currentHotel'), $data);
        AuditLogger::record($request, 'financial_communications_updated', 'finance', 'sensitive', 'Financial email settings updated.', app('currentHotel'));

        return back()->with('success', 'Financial communication settings saved.');
    }

    public function send(Request $request, FinancialCommunicator $communicator): RedirectResponse
    {
        $data = $request->validate(['document_type' => ['required', Rule::in(['invoice', 'statement', 'deposit', 'reminder'])], 'document_id' => ['required', 'integer'], 'recipient' => ['required', 'email', 'max:150'], 'subject' => ['nullable', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:5000']]);
        $delivery = $communicator->queue(app('currentHotel'), $data['document_type'], $data['document_id'], $data['recipient'], $request->user()->id, null, $data['subject'] ?? null, $data['body'] ?? null);
        AuditLogger::record($request, 'financial_document_queued', 'finance', 'sensitive', "{$delivery->document_type} queued for {$delivery->recipient}.", $delivery);

        return back()->with('success', 'Financial email queued for delivery.');
    }

    public function retry(Request $request, FinancialCommunication $delivery): RedirectResponse
    {
        abort_unless(in_array($delivery->status, ['failed', 'cancelled']), 422, 'Only failed deliveries can be retried.');
        $delivery->update(['status' => 'queued', 'last_error' => null, 'scheduled_for' => now()]);
        DeliverFinancialCommunication::dispatch($delivery->id)->afterCommit();
        AuditLogger::record($request, 'financial_delivery_retried', 'finance', 'sensitive', 'Financial email delivery retried.', $delivery);

        return back()->with('success', 'Delivery queued for retry.');
    }
}
