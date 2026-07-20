<?php
namespace Tests\Feature;
use App\Models\{Checkin,Guest,HousekeepingTask,Reservation,Room,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ReservationHousekeepingTest extends TestCase {
 use RefreshDatabase;
 public function test_verified_guest_can_move_from_reservation_through_housekeeping_release():void{
  $admin=User::factory()->create(['role'=>'super_admin']);$guest=Guest::create(['first_name'=>'Ada','last_name'=>'Guest','id_status'=>'verified']);$room=Room::create(['number'=>'501','type'=>'Suite','floor'=>5,'status'=>'available','price'=>250]);
  $this->actingAs($admin)->post(route('dashboard.bookings.store'),['guest_id'=>$guest->id,'room_id'=>'','arrival_date'=>today()->toDateString(),'departure_date'=>today()->addDays(2)->toDateString(),'guest_count'=>1,'room_type'=>'Suite','payment_status'=>'paid','total_amount'=>500,'amount_paid'=>500,'source'=>'direct','special_requests'=>''])->assertSessionHasNoErrors();
  $reservation=Reservation::first();$this->actingAs($admin)->post(route('dashboard.bookings.checkin',$reservation),['room_id'=>$room->id])->assertSessionHasNoErrors();
  $checkin=Checkin::first();$this->assertSame('checked_in',$reservation->fresh()->status);$this->assertSame('occupied',$room->fresh()->status);
  $this->actingAs($admin)->patch(route('dashboard.checkins.checkout',$checkin))->assertSessionHasNoErrors();$task=HousekeepingTask::first();
  $this->assertSame('cleaning',$room->fresh()->status);$this->assertNotNull($task);
  $worker=User::factory()->create(['role'=>'housekeeping','status'=>'active']);
  $checklist=collect(['Bathroom cleaned','Bedding changed','Minibar checked','Supplies restocked','Damage checked','Door lock tested'])->map(fn($label)=>['label'=>$label,'completed'=>true])->all();
  foreach(['in_progress','completed','inspected'] as $status)$this->actingAs($admin)->patch(route('dashboard.housekeeping.update',$task),['status'=>$status,'assigned_to'=>$worker->id,'priority'=>'normal','notes'=>'','checklist'=>$checklist])->assertSessionHasNoErrors();
  $this->assertSame('available',$room->fresh()->status);$this->assertSame('inspected',$task->fresh()->status);
 }
 public function test_unverified_guest_cannot_check_in():void{
  $admin=User::factory()->create(['role'=>'super_admin']);$guest=Guest::create(['first_name'=>'Pending','last_name'=>'Guest','id_status'=>'pending']);$room=Room::create(['number'=>'502','type'=>'Standard','floor'=>5,'status'=>'available','price'=>100]);$reservation=Reservation::create(['guest_id'=>$guest->id,'reference'=>'RS-PENDING','arrival_date'=>today(),'departure_date'=>today()->addDay(),'status'=>'confirmed','payment_status'=>'paid','created_by'=>$admin->id]);
  $this->actingAs($admin)->post(route('dashboard.bookings.checkin',$reservation),['room_id'=>$room->id])->assertStatus(422);$this->assertSame('available',$room->fresh()->status);
 }
 public function test_manual_request_can_be_assigned_and_notifies_worker():void{
  $admin=User::factory()->create(['role'=>'super_admin']);$worker=User::factory()->create(['role'=>'housekeeping','status'=>'active']);$room=Room::create(['number'=>'503','type'=>'Standard','floor'=>5,'status'=>'available','price'=>100]);
  $this->actingAs($admin)->post(route('dashboard.housekeeping.store'),['room_id'=>$room->id,'task_type'=>'service','assigned_to'=>$worker->id,'priority'=>'high','due_at'=>now()->addHour()->toDateTimeString(),'notes'=>'Replace linens'])->assertSessionHasNoErrors();
  $task=HousekeepingTask::first();$this->assertSame($worker->id,$task->assigned_to);$this->assertSame('cleaning',$room->fresh()->status);$this->assertNotNull($task->assigned_at);$this->assertCount(1,$worker->notifications);
 }
 public function test_reassignment_requires_a_reason():void{
  $admin=User::factory()->create(['role'=>'super_admin']);$first=User::factory()->create(['role'=>'housekeeping','status'=>'active']);$second=User::factory()->create(['role'=>'housekeeping','status'=>'active']);$room=Room::create(['number'=>'504','type'=>'Standard','floor'=>5,'status'=>'cleaning','price'=>100]);$task=HousekeepingTask::create(['room_id'=>$room->id,'assigned_to'=>$first->id,'status'=>'pending','priority'=>'normal']);
  $this->actingAs($admin)->patch(route('dashboard.housekeeping.update',$task),['status'=>'pending','assigned_to'=>$second->id,'priority'=>'normal','notes'=>''])->assertStatus(422);
  $this->actingAs($admin)->patch(route('dashboard.housekeeping.update',$task),['status'=>'pending','assigned_to'=>$second->id,'priority'=>'normal','notes'=>'','reassignment_reason'=>'Shift ended'])->assertSessionHasNoErrors();
  $this->assertSame($second->id,$task->fresh()->assigned_to);
 }
 public function test_staff_can_create_a_conversation_enabled_request_for_checked_in_guest():void{$admin=User::factory()->create(['role'=>'manager']);$guest=Guest::create(['first_name'=>'Live','last_name'=>'Guest']);$room=Room::create(['number'=>'505','type'=>'Standard','status'=>'occupied']);Checkin::create(['guest_id'=>$guest->id,'room_id'=>$room->id,'booking_reference'=>'LIVE-505','is_active'=>true]);$this->actingAs($admin)->post(route('dashboard.housekeeping.store'),['room_id'=>$room->id,'guest_id'=>$guest->id,'task_type'=>'service','assigned_to'=>'','priority'=>'normal','notes'=>'Deliver extra pillows'])->assertSessionHasNoErrors();$this->assertDatabaseHas('guest_service_requests',['guest_id'=>$guest->id,'room_id'=>$room->id,'type'=>'housekeeping','details'=>'Deliver extra pillows']);$this->assertDatabaseHas('guest_request_events',['event_type'=>'created_by_staff']);}
}
