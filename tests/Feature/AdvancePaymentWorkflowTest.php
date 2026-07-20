<?php

namespace Tests\Feature;

use App\Models\AdvancePayment;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Folios\FolioLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvancePaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_deposit_can_be_collected_allocated_and_refunded(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $guest = Guest::create(['first_name' => 'Deposit', 'last_name' => 'Guest']);
        $reservation = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-DEP', 'arrival_date' => today()->addDay(), 'departure_date' => today()->addDays(3), 'room_type' => 'King', 'status' => 'confirmed', 'total_amount' => 300]);
        $folio = Folio::forReservation($reservation);
        FolioItem::create(['folio_id' => $folio->id, 'type' => 'room_charge', 'description' => 'Room deposit balance', 'quantity' => 1, 'unit_amount' => 300, 'tax_amount' => 0, 'total_amount' => 300, 'service_date' => today()]);
        app(FolioLedger::class)->recalculate($folio);

        $this->actingAs($manager)->post(route('dashboard.deposits.store'), ['owner_type' => 'guest', 'guest_id' => $guest->id, 'reservation_id' => $reservation->id, 'method' => 'card', 'external_reference' => 'TERM-100', 'amount' => 300])->assertSessionHasNoErrors();
        $deposit = AdvancePayment::firstOrFail();
        $this->assertSame('300.00', $deposit->available_balance);
        $this->actingAs($manager)->post(route('dashboard.deposits.allocations.store', $deposit), ['target_type' => 'folio', 'folio_id' => $folio->id, 'amount' => 200])->assertSessionHasNoErrors();
        $this->assertSame('100.00', $deposit->fresh()->available_balance);
        $this->assertSame('100.00', $folio->fresh()->balance);
        $this->actingAs($manager)->post(route('dashboard.deposits.refund', $deposit), ['amount' => 50, 'reason' => 'Guest reduced the stay'])->assertSessionHasNoErrors();
        $this->assertSame('50.00', $deposit->fresh()->available_balance);
        $this->assertDatabaseHas('advance_payment_events', ['advance_payment_id' => $deposit->id, 'type' => 'refund', 'amount' => 50]);
    }

    public function test_deposit_collection_not_allocation_is_counted_by_night_audit(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $guest = Guest::create(['first_name' => 'Audit', 'last_name' => 'Deposit']);
        $this->actingAs($manager)->post(route('dashboard.deposits.store'), ['owner_type' => 'guest', 'guest_id' => $guest->id, 'method' => 'cash', 'amount' => 125]);
        $deposit = AdvancePayment::firstOrFail();
        $this->actingAs($manager)->post(route('dashboard.deposits.refund', $deposit), ['amount' => 25, 'reason' => 'Approved partial return']);
        $this->actingAs($manager)->post(route('dashboard.night-audit.close'), ['business_date' => today()->toDateString(), 'override_reason' => null])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('night_audits', ['payments' => 125, 'refunds' => 25]);
    }

    public function test_manager_can_view_ledger_and_print_receipt(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $guest = Guest::create(['first_name' => 'Receipt', 'last_name' => 'Holder']);
        $this->actingAs($manager)->post(route('dashboard.deposits.store'), ['owner_type' => 'guest', 'guest_id' => $guest->id, 'method' => 'bank_transfer', 'amount' => 80]);
        $deposit = AdvancePayment::firstOrFail();

        $this->actingAs($manager)->get(route('dashboard.deposits.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Deposits/Index')->where('summary.available', 80)->where('payments.0.receipt', $deposit->receipt_number));
        $this->actingAs($manager)->get(route('dashboard.deposits.receipt', $deposit))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Deposits/Receipt')->where('payment.receipt', $deposit->receipt_number));
    }
}
