<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceWorkOrder;
use App\Models\GuestServiceRequest;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\User;
use App\Notifications\MaintenanceAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MaintenanceWorkOrder::with(['room','assignee','assigner','inspector','guestServiceRequest.guest','guestServiceRequest.messages.user','guestServiceRequest.messages.guest','guestServiceRequest.events'])->latest('id');
        if ($request->user()->role === 'maintenance' && $request->string('scope')->value() !== 'all') $query->where('assigned_to', $request->user()->id);
        return Inertia::render('Dashboard/Maintenance/Maintenance', [
            'orders' => $query->get()->map(fn ($order) => $this->data($order)),
            'technicians' => User::where('role','maintenance')->where('status','active')->orderBy('name')->get(['id','name']),
            'rooms' => Room::orderBy('number')->get(['id','number','status']),
            'activeGuests' => \App\Models\Checkin::with('guest:id,first_name,last_name')->where('is_active',true)->whereNotNull('room_id')->get()->map(fn($c)=>['id'=>$c->guest_id,'name'=>trim($c->guest->first_name.' '.$c->guest->last_name),'roomId'=>$c->room_id]),
            'canInspect' => $request->user()->hasPermission('maintenance.inspect'),
            'isTechnician' => $request->user()->role === 'maintenance',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_id'=>['required','exists:rooms,id'], 'category'=>['required',Rule::in(['general','electrical','plumbing','hvac','lock','furniture','appliance'])],
            'title'=>['required','string','max:150'], 'description'=>['required','string','max:3000'], 'assigned_to'=>['nullable',$this->technicianRule()],
            'priority'=>['required',Rule::in(['low','normal','high','urgent'])], 'due_at'=>['nullable','date'],'guest_id'=>['nullable','exists:guests,id'],
        ]);
        $guestId=$validated['guest_id']??null;unset($validated['guest_id']);if($guestId)abort_unless(\App\Models\Checkin::where('guest_id',$guestId)->where('room_id',$validated['room_id'])->where('is_active',true)->exists(),422,'The selected guest is not actively checked into this room.');
        $order=MaintenanceWorkOrder::create([...$validated,'assigned_by'=>$validated['assigned_to']?$request->user()->id:null,'assigned_at'=>$validated['assigned_to']?now():null]);
        $order->room()->where('status','!=','occupied')->update(['status'=>'cleaning','lock_status'=>'locked']);
        $this->record($request,$order,'maintenance_requested',"Maintenance requested: {$order->title}.");
        if($guestId){$guestRequest=GuestServiceRequest::create(['guest_id'=>$guestId,'checkin_id'=>\App\Models\Checkin::where('guest_id',$guestId)->where('room_id',$order->room_id)->where('is_active',true)->value('id'),'room_id'=>$order->room_id,'type'=>'maintenance','status'=>$order->assigned_to?'assigned':'pending','priority'=>$order->priority,'details'=>$order->description,'maintenance_work_order_id'=>$order->id]);$guestRequest->events()->create(['user_id'=>$request->user()->id,'event_type'=>'created_by_staff','label'=>'Hotel staff created this request for the guest.','occurred_at'=>now()]);app(\App\Services\Notifications\MobileNotifier::class)->send($guestRequest->guest,'service','Maintenance request created','Hotel staff created a maintenance request for your room.',['type'=>'service_request_created','request_id'=>$guestRequest->id]);}
        if($order->assigned_to){$order->load('room','assignee');$order->assignee?->notify(new MaintenanceAlert($order,"You were assigned maintenance for Room {$order->room->number}."));}
        return back()->with('success','Maintenance work order created.');
    }

    public function update(Request $request, MaintenanceWorkOrder $order): RedirectResponse
    {
        $validated=$request->validate([
            'status'=>['required',Rule::in(['open','in_progress','repaired','inspected','cancelled'])], 'assigned_to'=>['nullable',$this->technicianRule()],
            'reassignment_reason'=>['nullable','string','max:1000'], 'priority'=>['required',Rule::in(['low','normal','high','urgent'])],
            'due_at'=>['nullable','date'], 'description'=>['required','string','max:3000'], 'repair_notes'=>['nullable','string','max:3000'],
            'inspection_notes'=>['nullable','string','max:3000'], 'cost'=>['required','numeric','min:0','max:99999999.99'],
        ]);
        $reassigned=$order->assigned_to&&$validated['assigned_to']&&(int)$order->assigned_to!==(int)$validated['assigned_to'];
        abort_if($reassigned&&blank($validated['reassignment_reason']??null),422,'Provide a reason when reassigning a work order.');
        abort_if($validated['status']==='in_progress'&&!$validated['assigned_to'],422,'Assign a technician before starting work.');
        abort_if($validated['status']==='repaired'&&blank($validated['repair_notes']??null),422,'Add repair notes before marking the work repaired.');
        $previous=$order->assigned_to;$data=$validated;
        if((int)$previous!==(int)$validated['assigned_to']){$data['assigned_by']=$request->user()->id;$data['assigned_at']=$validated['assigned_to']?now():null;}
        if($validated['status']==='in_progress'&&!$order->started_at)$data['started_at']=now();
        if($validated['status']==='repaired')$data['repaired_at']=now();
        if($validated['status']==='inspected'){
            abort_unless($request->user()->hasPermission('maintenance.inspect'),403);$data['inspected_at']=now();$data['inspected_by']=$request->user()->id;
            $order->room->update(['status'=>'available','lock_status'=>'locked']);$order->housekeepingTask?->update(['status'=>'inspected','inspected_at'=>now(),'inspected_by'=>$request->user()->id]);
        }
        if($validated['status']==='cancelled') { abort_unless($request->user()->hasPermission('maintenance.inspect'),403); $order->room->update(['status'=>'available','lock_status'=>'locked']); }
        $order->update($data);$order->load('room','assignee');$assignmentChanged=(int)$previous!==(int)$order->assigned_to;$this->syncGuestRequest($order,$assignmentChanged);
        $description=$assignmentChanged?'Maintenance assigned to '.($order->assignee?->name??'no technician').'.':"Maintenance changed to {$order->status}.";
        $this->record($request,$order,$assignmentChanged?'maintenance_assigned':'maintenance_'.$order->status,$description);
        if($assignmentChanged&&$order->assignee)$order->assignee->notify(new MaintenanceAlert($order,"You were assigned maintenance for Room {$order->room->number}."));
        if($order->status==='repaired')Notification::send(User::whereIn('role',['super_admin','manager'])->where('status','active')->get(),new MaintenanceAlert($order,"Room {$order->room->number} repair is ready for inspection."));
        return back()->with('success',$assignmentChanged?'Technician assigned.':'Work order updated.');
    }

    private function data(MaintenanceWorkOrder $order): array { return ['id'=>$order->id,'roomId'=>$order->room_id,'roomNumber'=>$order->room->number,'category'=>$order->category,'title'=>$order->title,'description'=>$order->description,'assignedTo'=>$order->assigned_to,'assignee'=>$order->assignee?->name,'assignedBy'=>$order->assigner?->name,'assignedAt'=>$order->assigned_at?->toISOString(),'reassignmentReason'=>$order->reassignment_reason,'priority'=>$order->priority,'status'=>$order->status,'dueAt'=>$order->due_at?->toISOString(),'isOverdue'=>$order->due_at?->isPast()&&!in_array($order->status,['inspected','cancelled']),'startedAt'=>$order->started_at?->toISOString(),'repairedAt'=>$order->repaired_at?->toISOString(),'repairNotes'=>$order->repair_notes,'cost'=>(float)$order->cost,'inspectedAt'=>$order->inspected_at?->toISOString(),'inspector'=>$order->inspector?->name,'inspectionNotes'=>$order->inspection_notes,'source'=>$order->guestServiceRequest?'Guest app':($order->housekeeping_task_id?'Housekeeping':'Manual'),'guestRequest'=>$this->guestRequestData($order->guestServiceRequest)]; }
    private function technicianRule(){return Rule::exists('users','id')->where(fn($query)=>$query->where('role','maintenance')->where('status','active'));}
    private function record(Request $request,MaintenanceWorkOrder $order,string $type,string $description):void{RoomEvent::create(['room_id'=>$order->room_id,'actor_id'=>$request->user()->id,'event_type'=>$type,'description'=>$description,'metadata'=>['work_order_id'=>$order->id,'cost'=>(float)$order->cost],'occurred_at'=>now()]);}
    private function syncGuestRequest(MaintenanceWorkOrder $order,bool $assignmentChanged):void{$status=match($order->status){'in_progress'=>'in_progress','repaired','inspected'=>'completed','cancelled'=>'cancelled',default=>$assignmentChanged&&$order->assigned_to?'assigned':'pending'};$request=GuestServiceRequest::where('maintenance_work_order_id',$order->id)->with('guest')->first();if($request&&$request->status!==$status){$request->update(['status'=>$status,'completed_at'=>$status==='completed'?now():null]);$request->events()->create(['user_id'=>auth()->id(),'event_type'=>'status_changed','label'=>'Request changed to '.str_replace('_',' ',$status).'.','metadata'=>['status'=>$status],'occurred_at'=>now()]);app(\App\Services\Notifications\MobileNotifier::class)->send($request->guest,'service','Maintenance request updated','Your maintenance request is now '.str_replace('_',' ',$status).'.',['type'=>'service_request_updated','request_id'=>$request->id,'status'=>$status]);}}
    private function guestRequestData(?GuestServiceRequest$r):?array{return$r?['id'=>$r->id,'guestName'=>trim($r->guest->first_name.' '.$r->guest->last_name),'type'=>$r->type,'details'=>$r->details,'priority'=>$r->priority,'status'=>$r->status,'createdAt'=>$r->created_at?->toISOString(),'unreadCount'=>$r->messages->whereNotNull('guest_id')->whereNull('read_by_staff_at')->count(),'messages'=>$r->messages->sortBy('created_at')->map(fn($m)=>['id'=>$m->id,'sender'=>$m->guest_id?'Guest':($m->user?->name??'Staff'),'message'=>$m->message,'internal'=>$m->internal,'attachmentUrl'=>$m->attachment_path?route('dashboard.guest-requests.attachment',[$r,$m]):null,'createdAt'=>$m->created_at?->toISOString()])->values(),'timeline'=>$r->events->sortBy('occurred_at')->map(fn($e)=>['label'=>$e->label,'occurredAt'=>$e->occurred_at?->toISOString()])->values()]:null;}
}
