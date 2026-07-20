<?php

namespace App\Services\Finance;

use App\Models\AccountingExportBatch;
use App\Models\AccountingProfile;
use App\Models\AdvancePaymentEvent;
use App\Models\CorporateInvoicePayment;
use App\Models\FolioItem;
use App\Models\FolioPayment;
use App\Models\NightAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingExporter
{
    public const DEFAULTS = [
        'cash' => ['1000', 'Cash'], 'card_clearing' => ['1010', 'Card clearing'], 'bank' => ['1020', 'Bank'], 'other_clearing' => ['1090', 'Other payment clearing'],
        'guest_receivables' => ['1100', 'Guest receivables'], 'corporate_receivables' => ['1110', 'Corporate receivables'], 'advance_deposits' => ['2100', 'Advance deposits liability'], 'tax_payable' => ['2200', 'Tax payable'],
        'room_revenue' => ['4000', 'Room revenue'], 'service_revenue' => ['4100', 'Service revenue'], 'fee_revenue' => ['4200', 'Fee and adjustment revenue'], 'forfeiture_revenue' => ['4300', 'Deposit forfeiture revenue'], 'credit_allowances' => ['4900', 'Credits and allowances'],
    ];

    public function profile(): AccountingProfile
    {
        $profile = AccountingProfile::firstOrCreate([], ['name' => 'Default accounting profile', 'driver' => 'file', 'active' => true]);
        foreach (self::DEFAULTS as $key => [$code, $name]) {
            $profile->mappings()->firstOrCreate(['key' => $key], ['account_code' => $code, 'account_name' => $name]);
        }

        return $profile->load('mappings');
    }

    public function generate(NightAudit $audit, int $userId): AccountingExportBatch
    {
        abort_unless($audit->status === 'closed', 422, 'Only a closed night audit can be exported.');
        $profile = $this->profile();
        $existing = AccountingExportBatch::whereDate('business_date', $audit->business_date)->whereNull('reversal_of_id')->latest()->first();
        abort_if($existing?->status === 'posted', 422, 'This accounting period is locked because its journal has been posted.');
        $batch = DB::transaction(function () use ($audit, $userId, $profile, $existing) {
            $batch = $existing ?: AccountingExportBatch::create(['accounting_profile_id' => $profile->id, 'night_audit_id' => $audit->id, 'batch_number' => 'GL-'.$audit->business_date->format('Ymd').'-'.strtoupper(Str::random(5)), 'business_date' => $audit->business_date, 'status' => 'draft']);
            $batch->entries()->delete();
            $lines = $this->lines($audit->business_date->toDateString());
            foreach ($lines as $index => $line) {
                $batch->entries()->create(['line_number' => $index + 1, ...$this->mapped($profile, $line)]);
            }
            $debits = round(collect($lines)->sum('debit'), 2);
            $credits = round(collect($lines)->sum('credit'), 2);
            abort_if(abs($debits - $credits) > 0.001, 422, 'Journal is out of balance by '.abs($debits - $credits).'.');
            $checksum = hash('sha256', json_encode($lines, JSON_THROW_ON_ERROR));
            $batch->update(['night_audit_id' => $audit->id, 'status' => 'validated', 'debit_total' => $debits, 'credit_total' => $credits, 'checksum' => $checksum, 'error' => null, 'generated_by' => $userId, 'generated_at' => now()]);

            return $batch;
        });

        return $batch->refresh();
    }

    public function post(AccountingExportBatch $batch, int $userId): void
    {
        abort_unless($batch->status === 'validated', 422, 'Only a validated balanced journal can be posted.');
        abort_if((float) $batch->debit_total !== (float) $batch->credit_total, 422, 'Journal is not balanced.');
        $batch->update(['status' => 'posted', 'posted_by' => $userId, 'posted_at' => now()]);
    }

    public function reverse(AccountingExportBatch $batch, int $userId): AccountingExportBatch
    {
        abort_unless($batch->status === 'posted' && ! $batch->reversal_of_id, 422, 'Only an original posted batch can be reversed.');
        abort_if(AccountingExportBatch::where('reversal_of_id', $batch->id)->exists(), 422, 'This batch already has a reversal.');

        return DB::transaction(function () use ($batch, $userId) {
            $reversal = AccountingExportBatch::create(['accounting_profile_id' => $batch->accounting_profile_id, 'night_audit_id' => $batch->night_audit_id, 'reversal_of_id' => $batch->id, 'batch_number' => 'REV-'.$batch->batch_number, 'business_date' => $batch->business_date, 'status' => 'posted', 'debit_total' => $batch->credit_total, 'credit_total' => $batch->debit_total, 'generated_by' => $userId, 'generated_at' => now(), 'posted_by' => $userId, 'posted_at' => now()]);
            foreach ($batch->entries()->orderBy('line_number')->get() as $entry) {
                $reversal->entries()->create(['line_number' => $entry->line_number, 'account_key' => $entry->account_key, 'account_code' => $entry->account_code, 'account_name' => $entry->account_name, 'description' => 'Reversal: '.$entry->description, 'reference' => $entry->reference, 'debit' => $entry->credit, 'credit' => $entry->debit, 'metadata' => ['reversal_of_entry' => $entry->id]]);
            }
            $reversal->update(['checksum' => hash('sha256', $reversal->entries()->orderBy('line_number')->get()->toJson())]);
            $batch->update(['status' => 'reversed']);

            return $reversal;
        });
    }

    private function lines(string $date): array
    {
        $lines = [];
        $add = function (string $key, float $debit, float $credit, string $description, ?string $reference = null, array $metadata = []) use (&$lines) {
            if (round($debit + $credit, 2) > 0) {
                $lines[] = compact('key', 'debit', 'credit', 'description', 'reference', 'metadata');
            }
        };
        FolioItem::with('folio.groupBooking')->where('voided', false)->whereDate('service_date', $date)->get()->each(function ($item) use ($add) {
            $ar = $item->folio?->group_booking_id ? 'corporate_receivables' : 'guest_receivables';
            $net = round((float) $item->total_amount - (float) $item->tax_amount, 2);
            $ref = $item->folio?->number;
            $revenue = $item->type === 'room_charge' ? 'room_revenue' : (in_array($item->type, ['service']) ? 'service_revenue' : 'fee_revenue');
            $add($ar, (float) $item->total_amount, 0, $item->description, $ref, ['folio_item_id' => $item->id]);
            $add($revenue, 0, $net, $item->description, $ref);
            $add('tax_payable', 0, (float) $item->tax_amount, 'Tax: '.$item->description, $ref);
        });
        FolioPayment::with('folio')->where('status', 'completed')->whereDate('processed_at', $date)->get()->each(function ($payment) use ($add) {
            $ar = $payment->folio?->group_booking_id ? 'corporate_receivables' : 'guest_receivables';
            $clearing = $payment->method === 'advance_deposit' ? 'advance_deposits' : $this->clearing($payment->method);
            $ref = $payment->folio?->number;
            if ($payment->type === 'payment') {
                $add($clearing, (float) $payment->amount, 0, 'Folio payment', $ref);
                $add($ar, 0, (float) $payment->amount, 'Folio payment', $ref);
            }
            if ($payment->type === 'refund') {
                $add($ar, (float) $payment->amount, 0, 'Folio refund', $ref);
                $add($clearing, 0, (float) $payment->amount, 'Folio refund', $ref);
            }
        });
        CorporateInvoicePayment::with('invoice')->whereDate('processed_at', $date)->get()->each(function ($payment) use ($add) {
            $ref = $payment->invoice?->number;
            if ($payment->type === 'payment') {
                $add($payment->method === 'advance_deposit' ? 'advance_deposits' : $this->clearing($payment->method), (float) $payment->amount, 0, 'Corporate payment', $ref);
                $add('corporate_receivables', 0, (float) $payment->amount, 'Corporate payment', $ref);
            } else {
                $add('credit_allowances', (float) $payment->amount, 0, 'Corporate credit note', $ref);
                $add('corporate_receivables', 0, (float) $payment->amount, 'Corporate credit note', $ref);
            }
        });
        AdvancePaymentEvent::with('advancePayment')->whereDate('occurred_at', $date)->get()->each(function ($event) use ($add) {
            $ref = $event->advancePayment?->receipt_number;
            $clearing = $this->clearing($event->advancePayment?->method ?? 'other');
            if ($event->type === 'received') {
                $add($clearing, (float) $event->amount, 0, 'Advance payment received', $ref);
                $add('advance_deposits', 0, (float) $event->amount, 'Advance payment liability', $ref);
            }
            if ($event->type === 'refund') {
                $add('advance_deposits', (float) $event->amount, 0, 'Advance payment refund', $ref);
                $add($clearing, 0, (float) $event->amount, 'Advance payment refund', $ref);
            }
            if ($event->type === 'forfeit') {
                $add('advance_deposits', (float) $event->amount, 0, 'Advance payment forfeited', $ref);
                $add('forfeiture_revenue', 0, (float) $event->amount, 'Deposit forfeiture revenue', $ref);
            }
        });

        return $lines;
    }

    private function clearing(string $method): string
    {
        return match ($method) {
            'cash' => 'cash', 'card', 'external_terminal', 'provider' => 'card_clearing', 'bank_transfer' => 'bank', default => 'other_clearing'
        };
    }

    private function mapped(AccountingProfile $profile, array $line): array
    {
        $map = $profile->mappings->firstWhere('key', $line['key']);
        abort_unless($map, 422, "Missing account mapping: {$line['key']}");

        return ['account_key' => $line['key'], 'account_code' => $map->account_code, 'account_name' => $map->account_name, 'description' => $line['description'], 'reference' => $line['reference'], 'debit' => round($line['debit'], 2), 'credit' => round($line['credit'], 2), 'metadata' => $line['metadata']];
    }
}
