<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\LockDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class HotelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_details_show_device_network_data_and_linked_accounts(): void
    {
        $user=User::factory()->create();$guest=Guest::create(['first_name'=>'Jane','last_name'=>'Doe','email'=>'jane@example.com']);$linked=Guest::create(['first_name'=>'John','last_name'=>'Doe','email'=>'john@example.com']);$deviceId='17aa4f39-2502-4a67-91c0-622b22c253b5';
        $guest->devices()->create(['device_id'=>$deviceId,'name'=>'Jane Phone','platform'=>'ios','ip_address'=>'192.0.2.10','last_seen_at'=>now()]);
        $linked->devices()->create(['device_id'=>$deviceId,'name'=>'Shared Phone','platform'=>'ios','ip_address'=>'192.0.2.11','last_seen_at'=>now()->subHour()]);
        $this->actingAs($user)->get(route('dashboard.guests.show',$guest->id))->assertOk()->assertInertia(fn(Assert$page)=>$page
            ->where('devices.0.deviceId',$deviceId)
            ->where('devices.0.platform','ios')
            ->where('devices.0.ipAddress','192.0.2.10')
            ->where('devices.0.linkedGuests.0.id',$linked->id)
            ->where('devices.0.linkedGuests.0.email','john@example.com'));
    }

    public function test_room_assignment_closes_the_previous_stay_and_releases_its_room(): void
    {
        $user = User::factory()->create();
        $guest = Guest::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'id_status' => 'verified']);
        $oldRoom = Room::create(['number' => '101', 'type' => 'Standard', 'floor' => 1, 'status' => 'occupied', 'price' => 100]);
        $newRoom = Room::create(['number' => '102', 'type' => 'Standard', 'floor' => 1, 'status' => 'available', 'price' => 100]);
        $oldCheckin = Checkin::create(['guest_id' => $guest->id, 'room_id' => $oldRoom->id, 'check_in_date' => today(), 'booking_reference' => 'BK-OLD', 'is_active' => true]);

        $this->actingAs($user)->patch(route('dashboard.guests.assign-room', $guest->id), ['room_id' => $newRoom->id])->assertSessionHasNoErrors();

        $this->assertFalse($oldCheckin->fresh()->is_active);
        $this->assertSame('available', $oldRoom->fresh()->status);
        $this->assertSame('occupied', $newRoom->fresh()->status);
        $this->assertSame(1, $guest->checkins()->where('is_active', true)->count());
        $this->assertDatabaseHas('room_events', ['room_id' => $newRoom->id, 'guest_id' => $guest->id, 'event_type' => 'guest_assigned', 'actor_id' => $user->id]);
        $this->assertDatabaseHas('room_events', ['room_id' => $oldRoom->id, 'event_type' => 'room_released']);
    }

    public function test_checkout_marks_the_room_for_cleaning(): void
    {
        $user = User::factory()->create();
        $guest = Guest::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $room = Room::create(['number' => '201', 'type' => 'Deluxe', 'floor' => 2, 'status' => 'occupied', 'price' => 200]);
        $checkin = Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'booking_reference' => 'BK-CHECKOUT', 'is_active' => true]);
        $guest->devices()->create(['device_id' => 'f293526d-c468-4648-bb15-8adf5a52199c']);
        $guest->createToken('guest-mobile:f293526d-c468-4648-bb15-8adf5a52199c');

        $this->actingAs($user)->patch(route('dashboard.checkins.checkout', $checkin))->assertSessionHasNoErrors();

        $this->assertFalse($checkin->fresh()->is_active);
        $this->assertSame('cleaning', $room->fresh()->status);
        $this->assertNotNull($checkin->fresh()->check_out_date);
        $this->assertSame(1, $guest->tokens()->count());
        $this->assertNull($guest->devices()->first()->revoked_at);
        $this->assertDatabaseHas('room_events', ['room_id' => $room->id, 'event_type' => 'guest_checked_out']);
    }

    public function test_an_active_checkin_can_generate_a_one_time_room_key(): void
    {
        $user = User::factory()->create();
        $guest = Guest::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $room = Room::create(['number' => '301', 'type' => 'Suite', 'floor' => 3, 'status' => 'occupied', 'price' => 300]);
        LockDevice::create(['room_id'=>$room->id,'provider'=>'simulator','external_id'=>'test-lock-301','name'=>'Room 301 Lock','status'=>'online','battery_level'=>100]);
        $checkin = Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'booking_reference' => 'BK-KEY', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('dashboard.checkins.key', $checkin));

        $response->assertSessionHas('generatedKey');
        $this->assertNotNull($checkin->fresh()->access_key_hash);
        $this->assertNotNull($checkin->fresh()->access_key_expires_at);
        $this->assertDatabaseHas('room_events', ['room_id' => $room->id, 'event_type' => 'key_issued']);
    }

    public function test_lock_inventory_can_be_assigned_to_and_removed_from_a_room(): void
    {
        $user=User::factory()->create(['role'=>'super_admin']);
        $room=Room::create(['number'=>'401','type'=>'Penthouse','floor'=>4,'status'=>'available','price'=>400]);
        $device=LockDevice::create(['provider'=>'simulator','external_id'=>'inventory-401','name'=>'Inventory Lock','status'=>'online','battery_level'=>100]);
        $this->actingAs($user)->patch(route('dashboard.locks.assign',$device),['room_id'=>$room->id])->assertSessionHasNoErrors();
        $this->assertSame($room->id,$device->fresh()->room_id);
        $this->actingAs($user)->patch(route('dashboard.locks.unassign',$device))->assertSessionHasNoErrors();
        $this->assertNull($device->fresh()->room_id);
    }

    public function test_room_access_marker_can_be_rotated_and_is_audited(): void
    {
        $user=User::factory()->create(['role'=>'super_admin']);$room=Room::create(['number'=>'501','type'=>'Suite']);$old=$room->access_marker;
        $this->actingAs($user)->patch(route('dashboard.locks.marker.rotate',$room),['reason'=>'Printed marker was damaged'])->assertSessionHasNoErrors();
        $this->assertNotSame($old,$room->fresh()->access_marker);
        $this->assertDatabaseHas('room_events',['room_id'=>$room->id,'event_type'=>'access_marker_rotated']);
        $this->assertDatabaseHas('audit_events',['action'=>'access_marker_rotated','subject_id'=>$room->id]);
    }
}
