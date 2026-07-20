<?php

namespace Tests\Feature;

use App\Models\HousekeepingTask;
use App\Models\MaintenanceWorkOrder;
use App\Models\Room;
use App\Models\User;
use App\Models\Checkin;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_housekeeping_escalation_creates_a_work_order(): void
    {
        $manager=User::factory()->create(['role'=>'manager','status'=>'active']);
        $worker=User::factory()->create(['role'=>'housekeeping','status'=>'active']);
        $room=Room::create(['number'=>'601','type'=>'Standard','floor'=>6,'status'=>'cleaning','price'=>100]);
        $task=HousekeepingTask::create(['room_id'=>$room->id,'assigned_to'=>$worker->id,'status'=>'in_progress','priority'=>'high']);
        $this->actingAs($manager)->patch(route('dashboard.housekeeping.update',$task),['status'=>'maintenance_required','assigned_to'=>$worker->id,'priority'=>'high','notes'=>'','maintenance_notes'=>'Bathroom pipe is leaking'])->assertSessionHasNoErrors();
        $order=MaintenanceWorkOrder::first();
        $this->assertNotNull($order);$this->assertSame($task->id,$order->housekeeping_task_id);$this->assertSame('Bathroom pipe is leaking',$order->description);$this->assertSame('cleaning',$room->fresh()->status);
    }

    public function test_work_order_moves_through_repair_and_manager_inspection(): void
    {
        $manager=User::factory()->create(['role'=>'manager','status'=>'active']);
        $technician=User::factory()->create(['role'=>'maintenance','status'=>'active']);
        $room=Room::create(['number'=>'602','type'=>'Suite','floor'=>6,'status'=>'available','price'=>200]);
        $this->actingAs($manager)->post(route('dashboard.maintenance.store'),['room_id'=>$room->id,'category'=>'electrical','title'=>'Replace outlet','description'=>'Outlet is damaged','assigned_to'=>$technician->id,'priority'=>'urgent','due_at'=>now()->addHour()->toDateTimeString()])->assertSessionHasNoErrors();
        $order=MaintenanceWorkOrder::first();$this->assertSame('cleaning',$room->fresh()->status);$this->assertCount(1,$technician->notifications);
        $base=['assigned_to'=>$technician->id,'priority'=>'urgent','due_at'=>'','description'=>'Outlet is damaged','repair_notes'=>'Outlet replaced and tested','inspection_notes'=>'','cost'=>75];
        $this->actingAs($technician)->patch(route('dashboard.maintenance.update',$order),[...$base,'status'=>'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($technician)->patch(route('dashboard.maintenance.update',$order),[...$base,'status'=>'repaired'])->assertSessionHasNoErrors();
        $this->actingAs($manager)->patch(route('dashboard.maintenance.update',$order),[...$base,'status'=>'inspected','inspection_notes'=>'Repair verified'])->assertSessionHasNoErrors();
        $this->assertSame('available',$room->fresh()->status);$this->assertSame('inspected',$order->fresh()->status);$this->assertSame('75.00',$order->fresh()->cost);
    }

    public function test_technician_cannot_inspect_own_repair(): void
    {
        $technician=User::factory()->create(['role'=>'maintenance','status'=>'active']);$room=Room::create(['number'=>'603','type'=>'Standard','floor'=>6,'status'=>'cleaning','price'=>100]);$order=MaintenanceWorkOrder::create(['room_id'=>$room->id,'title'=>'Test','description'=>'Test issue','assigned_to'=>$technician->id,'priority'=>'normal','status'=>'repaired','repair_notes'=>'Fixed']);
        $this->actingAs($technician)->patch(route('dashboard.maintenance.update',$order),['status'=>'inspected','assigned_to'=>$technician->id,'priority'=>'normal','description'=>'Test issue','repair_notes'=>'Fixed','inspection_notes'=>'','cost'=>0])->assertForbidden();
    }
    public function test_staff_can_create_guest_linked_maintenance_without_marking_occupied_room_cleaning():void{$manager=User::factory()->create(['role'=>'manager']);$guest=Guest::create(['first_name'=>'Room','last_name'=>'Guest']);$room=Room::create(['number'=>'604','type'=>'Standard','status'=>'occupied']);Checkin::create(['guest_id'=>$guest->id,'room_id'=>$room->id,'booking_reference'=>'LIVE-604','is_active'=>true]);$this->actingAs($manager)->post(route('dashboard.maintenance.store'),['room_id'=>$room->id,'guest_id'=>$guest->id,'category'=>'hvac','title'=>'AC inspection','description'=>'Guest reports weak cooling','assigned_to'=>'','priority'=>'normal','due_at'=>''])->assertSessionHasNoErrors();$this->assertDatabaseHas('guest_service_requests',['guest_id'=>$guest->id,'room_id'=>$room->id,'type'=>'maintenance']);$this->assertSame('occupied',$room->fresh()->status);}
}
