<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\FinancialRule;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_rules_price_reservations_and_nightly_folios(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $guest = Guest::create(['first_name' => 'Price', 'last_name' => 'Guest']);
        $room = Room::create(['number' => '810', 'type' => 'King', 'status' => 'available', 'price' => 100]);
        FinancialRule::create(['name' => 'Hotel tax', 'type' => 'tax', 'calculation' => 'percentage', 'amount' => 10, 'application' => 'per_night', 'tax_exemptible' => true, 'active' => true, 'effective_from' => today()->subMonth()]);

        $this->actingAs($manager)->post(route('dashboard.bookings.store'), ['guest_id' => $guest->id, 'room_id' => $room->id, 'arrival_date' => today()->toDateString(), 'departure_date' => today()->addDays(2)->toDateString(), 'guest_count' => 1, 'room_type' => 'King', 'payment_status' => 'pending', 'total_amount' => 200, 'amount_paid' => 0, 'source' => 'direct', 'special_requests' => null])->assertSessionHasNoErrors();
        $reservation = Reservation::firstOrFail();
        $this->assertSame('220.00', $reservation->total_amount);
        $this->assertEquals(20.0, $reservation->pricing_snapshot['additions']);

        $room->update(['status' => 'occupied']);
        $checkin = Checkin::create(['reservation_id' => $reservation->id, 'guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today()->subDay(), 'check_out_date' => today()->addDay(), 'booking_reference' => $reservation->reference, 'is_active' => true]);
        $this->artisan('folios:post-nightly', ['date' => today()->toDateString()])->assertSuccessful();
        $item = FolioItem::where('idempotency_key', "nightly:{$checkin->id}:".today()->toDateString())->firstOrFail();
        $this->assertSame('10.00', $item->tax_amount);
        $this->assertSame('110.00', $item->total_amount);
    }

    public function test_configured_cancellation_fee_posts_and_exemptions_require_management(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $frontDesk = User::factory()->create(['role' => 'front_desk', 'status' => 'active']);
        $guest = Guest::create(['first_name' => 'Cancel', 'last_name' => 'Guest']);
        FinancialRule::create(['name' => 'Cancellation', 'type' => 'cancellation', 'calculation' => 'fixed', 'amount' => 50, 'application' => 'per_stay', 'active' => true, 'effective_from' => today()->subDay()]);
        $reservation = Reservation::create(['guest_id' => $guest->id, 'reference' => 'FIN-CANCEL', 'arrival_date' => today()->addDay(), 'departure_date' => today()->addDays(2), 'status' => 'confirmed', 'total_amount' => 200]);

        $this->actingAs($manager)->patch(route('dashboard.bookings.cancel', $reservation))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('folio_items', ['type' => 'cancellation', 'total_amount' => 50]);

        $this->actingAs($frontDesk)->post(route('dashboard.bookings.store'), ['guest_id' => $guest->id, 'arrival_date' => today()->addDays(3)->toDateString(), 'departure_date' => today()->addDays(4)->toDateString(), 'guest_count' => 1, 'room_type' => 'King', 'payment_status' => 'pending', 'total_amount' => 100, 'amount_paid' => 0, 'source' => 'direct', 'tax_exempt' => true, 'tax_exemption_reference' => 'CERT-1'])->assertForbidden();
    }
}
