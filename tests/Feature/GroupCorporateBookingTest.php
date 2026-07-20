<?php

namespace Tests\Feature;

use App\Models\CorporateAccount;
use App\Models\GroupBooking;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupCorporateBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_builds_group_rooming_list_with_negotiated_corporate_billing(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $guest = Guest::create(['first_name' => 'Group', 'last_name' => 'Guest']);
        $room = Room::create(['number' => '901', 'type' => 'King', 'status' => 'available', 'price' => 250]);
        $this->actingAs($manager)->post(route('dashboard.corporate-accounts.store'), ['name' => 'Acme Travel', 'code' => 'ACME', 'contact_name' => 'Ann Buyer', 'email' => 'ann@acme.test', 'phone' => '555-1000', 'billing_address' => '1 Company Road', 'tax_number' => 'TAX-1', 'credit_limit' => 500, 'payment_terms_days' => 30, 'notes' => 'Approved'])->assertSessionHasNoErrors();
        $account = CorporateAccount::firstOrFail();
        $this->actingAs($manager)->post(route('dashboard.groups.store'), ['name' => 'Acme Retreat', 'code' => 'RETREAT', 'corporate_account_id' => $account->id, 'contact_name' => 'Ann Buyer', 'contact_email' => 'ann@acme.test', 'contact_phone' => '555-1000', 'arrival_date' => today()->addDay()->toDateString(), 'departure_date' => today()->addDays(3)->toDateString(), 'billing_mode' => 'corporate', 'negotiated_nightly_rate' => 200, 'room_commitment' => 2, 'release_date' => today()->toDateString(), 'billing_instructions' => 'Bill lodging to Acme', 'notes' => ''])->assertSessionHasNoErrors();
        $group = GroupBooking::firstOrFail();
        $this->actingAs($manager)->post(route('dashboard.groups.members.store', $group), ['guest_id' => $guest->id, 'room_id' => $room->id, 'room_type' => 'King', 'guest_count' => 1, 'nightly_rate' => 200, 'billing_responsibility' => 'corporate', 'special_requests' => ''])->assertSessionHasNoErrors();
        $reservation = Reservation::firstOrFail();
        $this->assertSame('400.00', $reservation->total_amount);
        $this->assertSame('corporate', $reservation->billing_responsibility);
        $this->assertSame($account->id, $reservation->corporate_account_id);
        $this->actingAs($manager)->patch(route('dashboard.groups.status', $group), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->actingAs($manager)->get(route('dashboard.groups.index', ['group' => $group->id]))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Groups/Index')->where('selected.code', 'RETREAT')->where('selected.totalValue', 400)->where('accounts.0.outstanding', 400));
    }

    public function test_corporate_credit_limit_and_inactive_account_are_enforced(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $account = CorporateAccount::create(['name' => 'Limited Co', 'code' => 'LIMITED', 'status' => 'active', 'credit_limit' => 100, 'payment_terms_days' => 14]);
        $group = GroupBooking::create(['corporate_account_id' => $account->id, 'code' => 'LIMIT', 'name' => 'Limited Group', 'status' => 'tentative', 'contact_name' => 'Buyer', 'arrival_date' => today()->addDay(), 'departure_date' => today()->addDays(2), 'billing_mode' => 'corporate', 'room_commitment' => 1]);
        $guest = Guest::create(['first_name' => 'Credit', 'last_name' => 'Guest']);
        $this->actingAs($manager)->post(route('dashboard.groups.members.store', $group), ['guest_id' => $guest->id, 'room_id' => '', 'room_type' => 'King', 'guest_count' => 1, 'nightly_rate' => 150, 'billing_responsibility' => 'corporate', 'special_requests' => ''])->assertStatus(422);
        $account->update(['status' => 'on_hold', 'credit_limit' => 1000]);
        $this->actingAs($manager)->post(route('dashboard.groups.members.store', $group), ['guest_id' => $guest->id, 'room_id' => '', 'room_type' => 'King', 'guest_count' => 1, 'nightly_rate' => 50, 'billing_responsibility' => 'corporate', 'special_requests' => ''])->assertStatus(422);
        $this->assertDatabaseCount('reservations',0);
    }
}
