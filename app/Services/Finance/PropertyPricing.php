<?php

namespace App\Services\Finance;

use App\Models\FinancialRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PropertyPricing
{
    public function quote(float $base, CarbonInterface $date, ?string $roomType = null, int $nights = 1, bool $taxExempt = false, array $types = ['tax', 'fee', 'deposit'], ?string $application = null): array
    {
        $rules = $this->rules($date, $roomType)->whereIn('type', $types);
        if ($application) {
            $rules = $rules->where('application', $application);
        }
        $additions = 0.0;
        $includedTax = 0.0;
        $depositRequired = 0.0;
        $breakdown = [];
        foreach ($rules as $rule) {
            if ($taxExempt && $rule->type === 'tax' && $rule->tax_exemptible) {
                continue;
            }
            $basis = $base * max(1, $nights);
            $amount = $rule->calculation === 'percentage' ? round($basis * (float) $rule->amount / 100, 2) : round((float) $rule->amount * ($rule->application === 'per_night' ? max(1, $nights) : 1), 2);
            if ($rule->type === 'deposit') {
                $depositRequired += $amount;
            } elseif ($rule->price_inclusive && $rule->type === 'tax') {
                $amount = $rule->calculation === 'percentage' ? round($basis - ($basis / (1 + ((float) $rule->amount / 100))), 2) : min($basis, $amount);
                $includedTax += $amount;
            } else {
                $additions += $amount;
            }
            $breakdown[] = ['rule_id' => $rule->id, 'name' => $rule->name, 'type' => $rule->type, 'amount' => $amount, 'inclusive' => $rule->price_inclusive];
        }
        $subtotal = round($base * max(1, $nights), 2);

        return ['subtotal' => $subtotal, 'additions' => round($additions, 2), 'included_tax' => round($includedTax, 2), 'deposit_required' => round($depositRequired, 2), 'total' => round($subtotal + $additions, 2), 'breakdown' => $breakdown];
    }

    public function policy(string $type, float $base, CarbonInterface $date, ?string $roomType = null): array
    {
        return $this->quote($base, $date, $roomType, 1, false, [$type]);
    }

    private function rules(CarbonInterface $date, ?string $roomType): Collection
    {
        return FinancialRule::where('active', true)->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))->where(fn ($q) => $q->whereNull('room_type')->when($roomType, fn ($r) => $r->orWhere('room_type', $roomType)))->orderBy('id')->get();
    }
}
