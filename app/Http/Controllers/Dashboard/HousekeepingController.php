<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\GuestServiceRequest;
use App\Models\MaintenanceWorkOrder;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\User;
use App\Notifications\HousekeepingAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HousekeepingTask::with(['room', 'assignee', 'assigner','guestServiceRequest.guest','guestServiceRequest.messages.user','guestServiceRequest.messages.guest','guestServiceRequest.events'])->latest('id');

        if ($request->user()->role === 'housekeeping' && $request->string('scope')->value() !== 'all') {
            $query->where('assigned_to', $request->user()->id);
        }

        return Inertia::render('Dashboard/Housekeeping/Housekeeping', [
            'tasks' => $query->get()->map(fn (HousekeepingTask $task) => $this->taskData($task)),
            'workers' => User::where('role', 'housekeeping')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::orderBy('number')->get(['id', 'number', 'status']),
            'activeGuests' => \App\Models\Checkin::with('guest:id,first_name,last_name')->where('is_active',true)->whereNotNull('room_id')->get()->map(fn($c)=>['id'=>$c->guest_id,'name'=>trim($c->guest->first_name.' '.$c->guest->last_name),'roomId'=>$c->room_id]),
            'canInspect' => $request->user()->hasPermission('housekeeping.inspect'),
            'isHousekeeper' => $request->user()->role === 'housekeeping',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'task_type' => ['required', Rule::in(['turnover', 'service', 'inspection'])],
            'assigned_to' => ['nullable', $this->activeHousekeeperRule()],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'guest_id' => ['nullable','exists:guests,id'],
        ]);
        $guestId=$validated['guest_id']??null;unset($validated['guest_id']);
        if($guestId)abort_unless(\App\Models\Checkin::where('guest_id',$guestId)->where('room_id',$validated['room_id'])->where('is_active',true)->exists(),422,'The selected guest is not actively checked into this room.');

        $duplicate = HousekeepingTask::where('room_id', $validated['room_id'])
            ->whereNotIn('status', ['inspected', 'maintenance_required'])
            ->exists();
        abort_if($duplicate, 422, 'This room already has an active housekeeping task.');

        $task = HousekeepingTask::create([
            ...$validated,
            'status' => 'pending',
            'assigned_by' => $validated['assigned_to'] ? $request->user()->id : null,
            'assigned_at' => $validated['assigned_to'] ? now() : null,
            'checklist' => $this->defaultChecklist(),
        ]);
        $task->room()->where('status', '!=', 'occupied')->update(['status' => 'cleaning']);
        $this->record($request, $task, 'housekeeping_requested', 'Manual '.str_replace('_', ' ', $task->task_type).' housekeeping requested.');
        if($guestId){$guestRequest=GuestServiceRequest::create(['guest_id'=>$guestId,'checkin_id'=>\App\Models\Checkin::where('guest_id',$guestId)->where('room_id',$task->room_id)->where('is_active',true)->value('id'),'room_id'=>$task->room_id,'type'=>'housekeeping','status'=>$task->assigned_to?'assigned':'pending','priority'=>$task->priority,'details'=>$task->notes?:'Housekeeping requested by hotel staff.','housekeeping_task_id'=>$task->id]);$guestRequest->events()->create(['user_id'=>$request->user()->id,'event_type'=>'created_by_staff','label'=>'Hotel staff created this request for the guest.','occurred_at'=>now()]);app(\App\Services\Notifications\MobileNotifier::class)->send($guestRequest->guest,'service','Housekeeping request created','Hotel staff created a housekeeping request for your room.',['type'=>'service_request_created','request_id'=>$guestRequest->id]);}

        if ($task->assigned_to) {
            $task->load('room', 'assignee');
            $task->assignee?->notify(new HousekeepingAlert($task, "You were assigned to Room {$task->room->number}."));
        }

        return back()->with('success', 'Housekeeping task created.');
    }

    public function update(Request $request, HousekeepingTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'inspected', 'maintenance_required','cancelled'])],
            'assigned_to' => ['nullable', $this->activeHousekeeperRule()],
            'reassignment_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:100'],
            'checklist.*.completed' => ['required_with:checklist', 'boolean'],
            'maintenance_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $isReassignment = $task->assigned_to && $validated['assigned_to'] && (int) $task->assigned_to !== (int) $validated['assigned_to'];
        abort_if($isReassignment && blank($validated['reassignment_reason'] ?? null), 422, 'Provide a reason when reassigning an active task.');
        abort_if($validated['status'] === 'in_progress' && ! $validated['assigned_to'], 422, 'Assign a housekeeping worker before starting the job.');
        abort_if($validated['status'] === 'completed' && collect($validated['checklist'] ?? $task->checklist)->contains(fn ($item) => ! ($item['completed'] ?? false)), 422, 'Complete every checklist item before marking the room completed.');

        $previousAssignee = $task->assigned_to;
        $data = $validated;
        if ((int) $previousAssignee !== (int) $validated['assigned_to']) {
            $data['assigned_by'] = $request->user()->id;
            $data['assigned_at'] = $validated['assigned_to'] ? now() : null;
        }
        if ($validated['status'] === 'in_progress' && ! $task->started_at) $data['started_at'] = now();
        if ($validated['status'] === 'completed') $data['completed_at'] = now();
        if ($validated['status'] === 'inspected') {
            abort_unless($request->user()->hasPermission('housekeeping.inspect'), 403);
            $data['inspected_at'] = now();
            $data['inspected_by'] = $request->user()->id;
            $task->room->update(['status' => 'available', 'last_cleaned_at' => now()]);
        }
        if ($validated['status'] === 'maintenance_required') {
            abort_if(blank($validated['maintenance_notes'] ?? null), 422, 'Describe the maintenance issue.');
            $task->room->update(['status' => 'cleaning', 'lock_status' => 'locked']);
        }

        $task->update($data);
        $assignmentChanged = (int) $previousAssignee !== (int) $task->assigned_to;
        $this->syncGuestRequest($task, $assignmentChanged);
        if ($validated['status'] === 'maintenance_required') {
            MaintenanceWorkOrder::firstOrCreate(['housekeeping_task_id' => $task->id], [
                'room_id' => $task->room_id, 'category' => 'general', 'title' => 'Housekeeping maintenance report',
                'description' => $validated['maintenance_notes'], 'priority' => in_array($task->priority, ['high','urgent']) ? $task->priority : 'normal',
                'status' => 'open', 'due_at' => $task->due_at,
            ]);
        }
        $task->load('room', 'assignee');
        $description = $assignmentChanged
            ? 'Housekeeping assigned to '.($task->assignee?->name ?? 'no worker').($isReassignment ? ": {$validated['reassignment_reason']}" : '').'.'
            : 'Housekeeping task changed to '.str_replace('_', ' ', $validated['status']).'.';
        $this->record($request, $task, $assignmentChanged ? 'housekeeping_assigned' : 'housekeeping_'.$validated['status'], $description);

        if ($assignmentChanged && $task->assignee) {
            $task->assignee->notify(new HousekeepingAlert($task, "You were assigned to Room {$task->room->number}."));
        }
        if ($validated['status'] === 'completed') {
            $managers = User::whereIn('role', ['super_admin', 'manager'])->where('status', 'active')->get();
            Notification::send($managers, new HousekeepingAlert($task, "Room {$task->room->number} is ready for inspection."));
        }
        if ($validated['status'] === 'maintenance_required') {
            $managers = User::whereIn('role', ['super_admin', 'manager'])->where('status', 'active')->get();
            Notification::send($managers, new HousekeepingAlert($task, "Room {$task->room->number} requires maintenance."));
        }

        return back()->with('success', $assignmentChanged ? 'Housekeeping worker assigned.' : 'Housekeeping task updated.');
    }

    private function taskData(HousekeepingTask $task): array
    {
        return [
            'id' => $task->id,
            'roomNumber' => $task->room->number,
            'roomId' => $task->room_id,
            'taskType' => $task->task_type,
            'status' => $task->status,
            'priority' => $task->priority,
            'dueAt' => $task->due_at?->toISOString(),
            'isOverdue' => $task->due_at?->isPast() && ! in_array($task->status, ['inspected', 'maintenance_required']),
            'notes' => $task->notes,
            'checklist' => $task->checklist ?? $this->defaultChecklist(),
            'assignedTo' => $task->assigned_to,
            'assignee' => $task->assignee?->name,
            'assignedBy' => $task->assigner?->name,
            'assignedAt' => $task->assigned_at?->toISOString(),
            'reassignmentReason' => $task->reassignment_reason,
            'startedAt' => $task->started_at?->toISOString(),
            'completedAt' => $task->completed_at?->toISOString(),
            'maintenanceNotes' => $task->maintenance_notes,
            'guestRequest' => $this->guestRequestData($task->guestServiceRequest),
        ];
    }

    private function activeHousekeeperRule()
    {
        return Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'housekeeping')->where('status', 'active'));
    }

    private function defaultChecklist(): array
    {
        return collect(['Bathroom cleaned', 'Bedding changed', 'Minibar checked', 'Supplies restocked', 'Damage checked', 'Door lock tested'])
            ->map(fn ($label) => ['label' => $label, 'completed' => false])->all();
    }

    private function record(Request $request, HousekeepingTask $task, string $type, string $description): void
    {
        RoomEvent::create(['room_id' => $task->room_id, 'actor_id' => $request->user()->id, 'event_type' => $type, 'description' => $description, 'occurred_at' => now()]);
    }

    private function syncGuestRequest(HousekeepingTask $task, bool $assignmentChanged): void
    {
        $status = match ($task->status) {
            'in_progress' => 'in_progress',
            'completed', 'inspected' => 'completed',
            'maintenance_required' => 'escalated',
            default => $assignmentChanged && $task->assigned_to ? 'assigned' : 'pending',
        };
        $request=GuestServiceRequest::where('housekeeping_task_id',$task->id)->with('guest')->first();if($request&&$request->status!==$status){$request->update(['status'=>$status,'completed_at'=>$status==='completed'?now():null]);$request->events()->create(['user_id'=>auth()->id(),'event_type'=>'status_changed','label'=>'Request changed to '.str_replace('_',' ',$status).'.','metadata'=>['status'=>$status],'occurred_at'=>now()]);app(\App\Services\Notifications\MobileNotifier::class)->send($request->guest,'service','Service request updated','Your '.str_replace('_',' ',$request->type).' request is now '.str_replace('_',' ',$status).'.',['type'=>'service_request_updated','request_id'=>$request->id,'status'=>$status]);}
    }
    private function guestRequestData(?GuestServiceRequest $r):?array{return$r?['id'=>$r->id,'guestName'=>trim($r->guest->first_name.' '.$r->guest->last_name),'type'=>$r->type,'details'=>$r->details,'priority'=>$r->priority,'status'=>$r->status,'createdAt'=>$r->created_at?->toISOString(),'unreadCount'=>$r->messages->whereNotNull('guest_id')->whereNull('read_by_staff_at')->count(),'messages'=>$r->messages->sortBy('created_at')->map(fn($m)=>['id'=>$m->id,'sender'=>$m->guest_id?'Guest':($m->user?->name??'Staff'),'message'=>$m->message,'internal'=>$m->internal,'attachmentUrl'=>$m->attachment_path?route('dashboard.guest-requests.attachment',[$r,$m]):null,'createdAt'=>$m->created_at?->toISOString()])->values(),'timeline'=>$r->events->sortBy('occurred_at')->map(fn($e)=>['label'=>$e->label,'occurredAt'=>$e->occurred_at?->toISOString()])->values()]:null;}
}
