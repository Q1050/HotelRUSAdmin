<?php

namespace Tests\Feature;

use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GroupBooking;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Folios\FolioLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CorporateReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private function billingData(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $account = CorporateAccount::create(['name' => 'Corporate Travel', 'code' => 'CORP', 'status' => 'active', 'credit_limit' => 5000, 'payment_terms_days' => 30]);
        $group = GroupBooking::create(['corporate_account_id' => $account->id, 'code' => 'CONF', 'name' => 'Conference Team', 'status' => 'confirmed', 'contact_name' => 'Travel Manager', 'arrival_date' => today(), 'departure_date' => today()->addDays(2), 'billing_mode' => 'corporate', 'room_commitment' => 1]);
        $guest = Guest::create(['first_name' => 'Corporate', 'last_name' => 'Guest']);
        $reservation = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-CORP', 'arrival_date' => today(), 'departure_date' => today()->addDays(2), 'room_type' => 'King', 'status' => 'confirmed', 'group_booking_id' => $group->id, 'corporate_account_id' => $account->id, 'billing_responsibility' => 'corporate', 'total_amount' => 220]);
        $folio = Folio::forReservation($reservation);
        $item = FolioItem::create(['folio_id' => $folio->id, 'type' => 'room_charge', 'description' => 'Conference room night', 'quantity' => 1, 'unit_amount' => 200, 'tax_amount' => 20, 'total_amount' => 220, 'service_date' => today()]);
        app(FolioLedger::class)->recalculate($folio);

        return compact('manager', 'account', 'group', 'reservation', 'folio', 'item');
    }

    public function test_manager_consolidates_group_charges_and_issues_an_invoice(): void
    {
        ['manager' => $manager, 'group' => $group, 'folio' => $folio, 'item' => $item] = $this->billingData();

        $this->actingAs($manager)->post(route('dashboard.groups.master-folio.transfer', $group))->assertSessionHasNoErrors();
        $master = Folio::where('group_booking_id', $group->id)->firstOrFail();
        $this->assertTrue($item->fresh()->voided);
        $this->assertSame($master->id, $item->fresh()->transferred_to_folio_id);
        $this->assertSame('0.00', $folio->fresh()->balance);
        $this->assertSame('220.00', $master->fresh()->balance);

        $this->actingAs($manager)->post(route('dashboard.groups.invoices.store', $group))->assertSessionHasNoErrors();
        $invoice = CorporateInvoice::firstOrFail();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('220.00', $invoice->total);
        $this->assertSame(today()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->assertDatabaseHas('corporate_invoice_items', ['corporate_invoice_id' => $invoice->id, 'total_amount' => 220]);
        $this->actingAs($manager)->post(route('dashboard.groups.invoices.store', $group))->assertStatus(422);
    }

    public function test_payments_and_credits_close_a_corporate_invoice(): void
    {
        ['manager' => $manager, 'group' => $group] = $this->billingData();
        $this->actingAs($manager)->post(route('dashboard.groups.master-folio.transfer', $group));
        $this->actingAs($manager)->post(route('dashboard.groups.invoices.store', $group));
        $invoice = CorporateInvoice::firstOrFail();

        $this->actingAs($manager)->post(route('dashboard.corporate-invoices.payments.store', $invoice), ['amount' => 120, 'method' => 'bank_transfer', 'reference' => 'BANK-1'])->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->balance);
        $this->actingAs($manager)->post(route('dashboard.corporate-invoices.credits.store', $invoice), ['amount' => 100, 'notes' => 'Approved service recovery'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_receivables_page_and_statement_are_available_to_managers(): void
    {
        ['manager' => $manager, 'account' => $account, 'group' => $group] = $this->billingData();
        $this->actingAs($manager)->post(route('dashboard.groups.master-folio.transfer', $group));
        $this->actingAs($manager)->post(route('dashboard.groups.invoices.store', $group));
        $invoice = CorporateInvoice::firstOrFail();

        $this->actingAs($manager)->get(route('dashboard.receivables.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Receivables/Index')->where('invoices.0.number', $invoice->number)->where('accounts.0.balance', 220));
        $this->actingAs($manager)->get(route('dashboard.corporate-invoices.show', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Receivables/Show')->where('invoice.number', $invoice->number)->has('invoice.items', 1));
        $this->actingAs($manager)->get(route('dashboard.corporate-accounts.statement', $account))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
