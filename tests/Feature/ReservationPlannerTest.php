<?php

namespace Tests\Feature;

use App\Models\{Guest, InventoryBlock, Reservation, Room, RoomRateRule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationPlannerTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $guest = Guest::create(['first_name' => 'Planner', 'last_name' => 'Guest']);
        $room = Room::create(['number' => '601', 'type' => 'Suite', 'floor' => 6, 'status' => 'available', 'price' => 250]);
        return [$admin, $guest, $room];
    }

    public function test_staff_can_open_the_room_planner(): void
    {
        [$admin] = $this->data();
        $this->actingAs($admin)->get(route('dashboard.bookings.planner', ['start' => today()->toDateString()]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Bookings/Planner')->has('dates', 7)->has('rooms', 1)->where('roomTypes.0', 'Suite'));
    }

    public function test_reservation_cannot_be_moved_onto_an_overlapping_room(): void
    {
        [$admin, $guest, $room] = $this->data();
        Reservation::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'reference' => 'RS-FIRST', 'arrival_date' => today()->addDay(), 'departure_date' => today()->addDays(4), 'room_type' => 'Suite', 'status' => 'confirmed']);
        $moving = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-MOVING', 'arrival_date' => today()->addDays(7), 'departure_date' => today()->addDays(9), 'room_type' => 'Suite', 'status' => 'confirmed']);

        $this->actingAs($admin)->patch(route('dashboard.bookings.move', $moving), ['room_id' => $room->id, 'arrival_date' => today()->addDays(2)->toDateString()])->assertStatus(422);
        $this->assertNull($moving->fresh()->room_id);
    }

    public function test_valid_drag_move_preserves_stay_length(): void
    {
        [$admin, $guest, $room] = $this->data();
        $reservation = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-VALID', 'arrival_date' => today()->addDay(), 'departure_date' => today()->addDays(4), 'room_type' => 'Suite', 'status' => 'confirmed']);
        $this->actingAs($admin)->patch(route('dashboard.bookings.move', $reservation), ['room_id' => $room->id, 'arrival_date' => today()->addDays(10)->toDateString()])->assertSessionHasNoErrors();
        $reservation->refresh();
        $this->assertSame($room->id, $reservation->room_id);
        $this->assertSame(today()->addDays(13)->toDateString(), $reservation->departure_date->toDateString());
    }

    public function test_inventory_blocks_and_minimum_stay_rules_are_enforced(): void
    {
        [$admin, $guest, $room] = $this->data();
        RoomRateRule::create(['room_type' => 'Suite', 'start_date' => today(), 'end_date' => today()->addMonth(), 'minimum_stay' => 3, 'closed_to_arrival' => false]);
        $short = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-SHORT', 'arrival_date' => today()->addDays(10), 'departure_date' => today()->addDays(12), 'room_type' => 'Suite', 'status' => 'confirmed']);
        $this->actingAs($admin)->patch(route('dashboard.bookings.move', $short), ['room_id' => $room->id, 'arrival_date' => today()->addDays(10)->toDateString()])->assertStatus(422);

        InventoryBlock::create(['room_id' => $room->id, 'name' => 'Owner hold', 'start_date' => today()->addDays(20), 'end_date' => today()->addDays(22), 'quantity' => 1, 'status' => 'active']);
        $long = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-BLOCKED', 'arrival_date' => today()->addDays(20), 'departure_date' => today()->addDays(23), 'room_type' => 'Suite', 'status' => 'confirmed']);
        $this->actingAs($admin)->patch(route('dashboard.bookings.move', $long), ['room_id' => $room->id, 'arrival_date' => today()->addDays(20)->toDateString()])->assertStatus(422);
    }
}
