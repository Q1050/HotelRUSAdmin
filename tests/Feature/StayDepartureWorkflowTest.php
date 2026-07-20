<?php

namespace Tests\Feature;

use App\Models\{Checkin, Guest, GuestDevice, Reservation, Room, StayDeparture, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayDepartureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function stay(string $role='manager'): array
    {
        $staff=User::factory()->create(['role'=>$role]);
        $guest=Guest::create(['first_name'=>'Active','last_name'=>'Guest','id_status'=>'verified','email'=>'active@example.test']);
        $room=Room::create(['number'=>'710','type'=>'Suite','status'=>'occupied','lock_status'=>'locked','price'=>200]);
        $reservation=Reservation::create(['guest_id'=>$guest->id,'room_id'=>$room->id,'reference'=>'RS-DEPART','arrival_date'=>today()->subDay(),'departure_date'=>today()->addDays(3),'room_type'=>'Suite','status'=>'checked_in']);
        $checkin=Checkin::create(['reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'room_id'=>$room->id,'check_in_date'=>today()->subDay(),'check_out_date'=>today()->addDays(3),'booking_reference'=>'RS-DEPART','payment_status'=>'paid','is_active'=>true]);
        return[$staff,$guest,$room,$reservation,$checkin];
    }

    public function test_early_checkout_keeps_the_guests_mobile_account_signed_in():void
    {
        [$staff,$guest,$room,$reservation,$checkin]=$this->stay('front_desk');
        $device=GuestDevice::create(['guest_id'=>$guest->id,'device_id'=>'11111111-1111-4111-8111-111111111111','platform'=>'ios','last_seen_at'=>now()]);
        $guest->createToken('guest-mobile:test-device');
        $this->actingAs($staff)->patch(route('dashboard.checkins.checkout',$checkin),['departure_type'=>'early','reason'=>'Travel plans changed','financial_resolution'=>'partial_refund','refund_amount'=>100])->assertSessionHasNoErrors();
        $this->assertNull($device->fresh()->revoked_at);$this->assertCount(1,$guest->tokens);
        $this->assertSame('early',StayDeparture::first()->type);$this->assertSame('cleaning',$room->fresh()->status);$this->assertSame('checked_out',$reservation->fresh()->status);
    }

    public function test_forced_checkout_requires_management_and_can_add_do_not_rent():void
    {
        [$frontDesk,$guest,,, $checkin]=$this->stay('front_desk');
        $this->actingAs($frontDesk)->patch(route('dashboard.checkins.checkout',$checkin),['departure_type'=>'forced','reason'=>'Threatened staff'])->assertForbidden();
        $manager=User::factory()->create(['role'=>'manager']);
        GuestDevice::create(['guest_id'=>$guest->id,'device_id'=>'22222222-2222-4222-8222-222222222222','platform'=>'android','last_seen_at'=>now()]);$guest->createToken('guest-mobile:eviction-device');
        $this->actingAs($manager)->patch(route('dashboard.checkins.checkout',$checkin),['departure_type'=>'forced','reason'=>'Threatened staff','security_involved'=>true,'do_not_rent'=>true,'financial_resolution'=>'charge_balance'])->assertSessionHasNoErrors();
        $this->assertNotNull($guest->fresh()->do_not_rent_at);$this->assertCount(0,$guest->tokens);$this->assertNotNull($guest->devices()->first()->revoked_at);
        $this->assertDatabaseHas('stay_departures',['checkin_id'=>$checkin->id,'type'=>'forced','security_involved'=>true,'do_not_rent'=>true]);
    }

    public function test_management_can_suspend_and_restore_access_without_ending_stay():void
    {
        [$manager,,,,$checkin]=$this->stay();
        $this->actingAs($manager)->patch(route('dashboard.checkins.suspend-access',$checkin),['reason'=>'Investigating a safety complaint'])->assertSessionHasNoErrors();
        $this->assertTrue($checkin->fresh()->is_active);$this->assertNotNull($checkin->fresh()->access_suspended_at);
        $this->actingAs($manager)->post(route('dashboard.checkins.key',$checkin),['type'=>'mobile'])->assertStatus(422);
        $this->actingAs($manager)->patch(route('dashboard.checkins.restore-access',$checkin),['reason'=>'Issue resolved'])->assertSessionHasNoErrors();
        $this->assertNull($checkin->fresh()->access_suspended_at);
    }

    public function test_do_not_rent_guest_cannot_be_checked_in_again():void
    {
        [$manager,$guest,$room,$reservation]=$this->stay();$guest->update(['do_not_rent_at'=>now(),'do_not_rent_reason'=>'Prior eviction']);$room->update(['status'=>'available']);$reservation->update(['status'=>'confirmed']);
        $this->actingAs($manager)->post(route('dashboard.bookings.checkin',$reservation),['room_id'=>$room->id])->assertStatus(422);
    }
}
