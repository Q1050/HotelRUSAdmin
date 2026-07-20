<?php

namespace Tests\Feature;

use App\Models\{Hotel, HotelFeatureOverride, HotelSubscription, Plan, Room, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaasEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_only_sees_its_rooms_and_unpaid_module_is_blocked(): void
    {
        $defaultHotel = Hotel::where('slug', 'default-hotel')->firstOrFail();
        Room::create(['hotel_id'=>$defaultHotel->id,'number'=>'A-101','type'=>'Standard','floor'=>1,'status'=>'available','lock_status'=>'locked','price'=>100]);

        $hotel = $this->coreHotel();
        $user = User::factory()->create(['hotel_id'=>$hotel->id,'role'=>'super_admin']);
        Room::create(['hotel_id'=>$hotel->id,'number'=>'B-202','type'=>'Suite','floor'=>2,'status'=>'available','lock_status'=>'locked','price'=>200]);

        $this->actingAs($user)->get(route('dashboard.rooms'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Rooms/Rooms')
            ->has('rooms', 1)
            ->where('rooms.0.roomNumber', 'B-202')
        );
        $this->actingAs($user)->get(route('dashboard.maintenance.index'))->assertForbidden();
    }

    public function test_feature_override_can_enable_an_optional_module(): void
    {
        $hotel = $this->coreHotel();
        $user = User::factory()->create(['hotel_id'=>$hotel->id,'role'=>'super_admin']);
        HotelFeatureOverride::create(['hotel_id'=>$hotel->id,'feature'=>'maintenance','enabled'=>true]);

        $this->actingAs($user)->get(route('dashboard.maintenance.index'))->assertOk();
    }

    public function test_plan_room_limit_is_enforced_server_side(): void
    {
        $hotel = $this->coreHotel();
        $user = User::factory()->create(['hotel_id'=>$hotel->id,'role'=>'super_admin']);
        foreach (range(1, 25) as $number) {
            Room::create(['hotel_id'=>$hotel->id,'number'=>"C-$number",'type'=>'Standard','floor'=>1,'status'=>'available','lock_status'=>'locked','price'=>100]);
        }

        $this->actingAs($user)->post(route('dashboard.rooms.store'), [
            'number'=>'C-26','type'=>'Standard','floor'=>1,'status'=>'available','lockStatus'=>'locked','price'=>100,
        ])->assertStatus(422);
        $this->assertDatabaseMissing('rooms', ['hotel_id'=>$hotel->id,'number'=>'C-26']);
    }

    private function coreHotel(): Hotel
    {
        $hotel = Hotel::create(['name'=>'Core Hotel','slug'=>'core-hotel','status'=>'active','timezone'=>'America/Jamaica','currency'=>'JMD']);
        HotelSubscription::create(['hotel_id'=>$hotel->id,'plan_id'=>Plan::where('key','core')->firstOrFail()->id,'status'=>'active']);
        return $hotel;
    }
}
