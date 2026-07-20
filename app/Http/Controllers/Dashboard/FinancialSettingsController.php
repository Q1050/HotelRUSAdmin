<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FinancialRule;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialSettingsController extends Controller
{
    public function index(): Response
    {
        $hotel = app('currentHotel');

        return Inertia::render('Settings/Financial', ['currency' => $hotel->currency, 'timezone' => $hotel->timezone, 'rules' => FinancialRule::latest('effective_from')->latest('id')->get()->map(fn ($rule) => ['id' => $rule->id, 'name' => $rule->name, 'type' => $rule->type, 'calculation' => $rule->calculation, 'amount' => (float) $rule->amount, 'application' => $rule->application, 'roomType' => $rule->room_type, 'priceInclusive' => $rule->price_inclusive, 'taxExemptible' => $rule->tax_exemptible, 'active' => $rule->active, 'effectiveFrom' => $rule->effective_from->toDateString(), 'effectiveUntil' => $rule->effective_until?->toDateString()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data['type'] !== 'tax') {
            $data['price_inclusive'] = false;
            $data['tax_exemptible'] = false;
        }
        $rule = FinancialRule::create([...$data, 'created_by' => $request->user()->id]);
        AuditLogger::record($request, 'financial_rule_created', 'finance', 'sensitive', "Financial rule {$rule->name} created.", $rule);

        return back()->with('success', 'Financial rule created. Existing reservations remain unchanged.');
    }

    public function update(Request $request, FinancialRule $rule): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean'], 'effective_until' => ['nullable', 'date', 'after_or_equal:'.$rule->effective_from->toDateString()]]);
        $rule->update($data);
        AuditLogger::record($request, 'financial_rule_status_changed', 'finance', 'sensitive', "Financial rule {$rule->name} updated.", $rule);

        return back()->with('success', 'Rule status and end date updated. Create a new rule to change its rate without rewriting history.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['tax', 'fee', 'deposit', 'cancellation', 'no_show', 'early_departure'])], 'calculation' => ['required', Rule::in(['percentage', 'fixed'])], 'amount' => ['required', 'numeric', 'min:0', 'max:10000000'], 'application' => ['required', Rule::in(['per_stay', 'per_night'])], 'room_type' => ['nullable', 'string', 'max:50'], 'price_inclusive' => ['required', 'boolean'], 'tax_exemptible' => ['required', 'boolean'], 'active' => ['required', 'boolean'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
    }
}
