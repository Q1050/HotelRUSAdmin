<?php

namespace App\Services\Finance;

use App\Jobs\DeliverFinancialCommunication;
use App\Models\AdvancePayment;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\FinancialCommunication;
use App\Models\Hotel;
use Illuminate\Support\Facades\URL;

class FinancialCommunicator
{
    public function settings(Hotel $hotel): array
    {
        return array_merge(['enabled' => false, 'reply_to' => data_get($hotel->settings, 'branding.support_email'), 'due_days_before' => 3, 'overdue_repeat_days' => 7, 'invoice_subject' => '{{hotel}} invoice {{number}}', 'invoice_body' => "Hello,\n\nInvoice {{number}} has a balance of {{balance}} due {{due_date}}. The PDF invoice is attached.", 'statement_subject' => '{{hotel}} account statement', 'statement_body' => "Hello,\n\nYour current account statement is attached.", 'deposit_subject' => '{{hotel}} payment receipt {{number}}', 'deposit_body' => "Hello,\n\nThank you. Your advance-payment receipt {{number}} for {{amount}} is attached.", 'reminder_subject' => 'Payment reminder: {{number}}', 'reminder_body' => "Hello,\n\nThis is a reminder that invoice {{number}} has {{balance}} outstanding and was due {{due_date}}."], data_get($hotel->settings, 'financial_communications', []));
    }

    public function updateSettings(Hotel $hotel, array $settings): void
    {
        $all = $hotel->settings ?? [];
        $all['financial_communications'] = $settings;
        $hotel->update(['settings' => $all]);
    }

    public function queue(Hotel $hotel, string $type, int $documentId, string $recipient, ?int $userId = null, ?string $dedupeKey = null, ?string $customSubject = null, ?string $customBody = null): FinancialCommunication
    {
        $settings = $this->settings($hotel);
        $context = $this->context($hotel, $type, $documentId);
        $template = $type === 'reminder' ? 'reminder' : $type;
        $attributes = ['hotel_id' => $hotel->id, 'document_type' => $type, 'document_id' => $documentId, 'recipient' => $recipient, 'subject' => $this->render($customSubject ?: $settings[$template.'_subject'], $context), 'body' => $this->render($customBody ?: $settings[$template.'_body'], $context), 'status' => 'queued', 'scheduled_for' => now(), 'created_by' => $userId];
        $delivery = $dedupeKey ? FinancialCommunication::withoutGlobalScopes()->firstOrCreate(['hotel_id' => $hotel->id, 'dedupe_key' => $dedupeKey], $attributes) : FinancialCommunication::withoutGlobalScopes()->create($attributes);
        if ($delivery->wasRecentlyCreated || in_array($delivery->status, ['failed', 'cancelled'])) {
            $delivery->update(['status' => 'queued', 'last_error' => null, 'scheduled_for' => now()]);
            DeliverFinancialCommunication::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    public function document(FinancialCommunication $delivery): array
    {
        $hotel = Hotel::findOrFail($delivery->hotel_id);
        $lines = [$hotel->name, strtoupper(str_replace('_', ' ', $delivery->document_type)), ''];
        if (in_array($delivery->document_type, ['invoice', 'reminder'])) {
            $invoice = CorporateInvoice::withoutGlobalScopes()->with(['account', 'group', 'items'])->where('hotel_id', $hotel->id)->findOrFail($delivery->document_id);
            $lines = [...$lines, "Invoice: {$invoice->number}", "Account: {$invoice->account?->name}", 'Issue date: '.$invoice->issue_date->toDateString(), 'Due date: '.$invoice->due_date->toDateString(), 'Status: '.$invoice->status, '', ...$invoice->items->map(fn ($item) => $item->description.'  '.$invoice->currency.' '.$item->total_amount)->all(), '', "Total: {$invoice->currency} {$invoice->total}", "Paid: {$invoice->currency} {$invoice->paid_total}", "Balance: {$invoice->currency} {$invoice->balance}"];
            $name = $invoice->number.'.pdf';
        } elseif ($delivery->document_type === 'statement') {
            $account = CorporateAccount::withoutGlobalScopes()->where('hotel_id', $hotel->id)->findOrFail($delivery->document_id);
            $invoices = CorporateInvoice::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('corporate_account_id', $account->id)->orderBy('issue_date')->get();
            $lines = [...$lines, "Account: {$account->name} ({$account->code})", '', ...$invoices->map(fn ($invoice) => "{$invoice->number} | {$invoice->issue_date->toDateString()} | {$invoice->status} | {$invoice->currency} {$invoice->balance}")->all(), '', 'Outstanding: '.$hotel->currency.' '.$invoices->whereNotIn('status', ['paid', 'void'])->sum('balance')];
            $name = $account->code.'-statement.pdf';
        } else {
            $payment = AdvancePayment::withoutGlobalScopes()->with(['guest', 'corporateAccount', 'reservation', 'groupBooking'])->where('hotel_id', $hotel->id)->findOrFail($delivery->document_id);
            $owner = $payment->guest ? trim($payment->guest->first_name.' '.$payment->guest->last_name) : $payment->corporateAccount?->name;
            $lines = [...$lines, "Receipt: {$payment->receipt_number}", "Received from: {$owner}", 'Received: '.$payment->received_at->toDateTimeString(), "Method: {$payment->method}", "Amount: {$hotel->currency} {$payment->amount}", "Allocated: {$hotel->currency} {$payment->allocated_total}", "Available credit: {$hotel->currency} {$payment->available_balance}"];
            $name = $payment->receipt_number.'.pdf';
        }

        return ['name' => $name, 'pdf' => app(SimplePdf::class)->make($lines), 'hotel' => $hotel];
    }

    public function html(FinancialCommunication $delivery, Hotel $hotel): string
    {
        $branding = data_get($hotel->settings, 'branding', []);
        $name = $branding['display_name'] ?? $hotel->name;
        $color = $branding['primary_color'] ?? '#172554';
        $pixel = URL::temporarySignedRoute('communications.open', now()->addDays(30), ['delivery' => $delivery->id]);

        return '<div style="font-family:Arial,sans-serif;color:#1f2937;max-width:640px;margin:auto"><div style="background:'.e($color).';color:white;padding:22px;border-radius:10px 10px 0 0"><h2 style="margin:0">'.e($name).'</h2></div><div style="padding:28px;border:1px solid #e5e7eb">'.nl2br(e($delivery->body)).'<p style="margin-top:24px;color:#6b7280;font-size:12px">This financial document was sent by '.e($name).'.</p></div><img src="'.e($pixel).'" width="1" height="1" alt=""></div>';
    }

    private function context(Hotel $hotel, string $type, int $id): array
    {
        $context = ['hotel' => data_get($hotel->settings, 'branding.display_name', $hotel->name)];
        if (in_array($type, ['invoice', 'reminder'])) {
            $invoice = CorporateInvoice::withoutGlobalScopes()->where('hotel_id', $hotel->id)->findOrFail($id);

            return $context + ['number' => $invoice->number, 'balance' => $invoice->currency.' '.$invoice->balance, 'amount' => $invoice->currency.' '.$invoice->total, 'due_date' => $invoice->due_date->toDateString()];
        }
        if ($type === 'deposit') {
            $payment = AdvancePayment::withoutGlobalScopes()->where('hotel_id', $hotel->id)->findOrFail($id);

            return $context + ['number' => $payment->receipt_number, 'amount' => $hotel->currency.' '.$payment->amount, 'balance' => $hotel->currency.' '.$payment->available_balance, 'due_date' => ''];
        }

        return $context + ['number' => '', 'amount' => '', 'balance' => '', 'due_date' => ''];
    }

    private function render(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }
}
