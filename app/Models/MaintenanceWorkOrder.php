<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWorkOrder extends Model
{
    use Concerns\BelongsToHotel;
    protected $fillable = ['hotel_id','room_id','housekeeping_task_id','category','title','description','assigned_to','assigned_by','assigned_at','reassignment_reason','priority','status','due_at','started_at','repaired_at','repair_notes','cost','inspected_at','inspected_by','inspection_notes'];
    protected function casts(): array { return ['assigned_at'=>'datetime','due_at'=>'datetime','started_at'=>'datetime','repaired_at'=>'datetime','inspected_at'=>'datetime','cost'=>'decimal:2']; }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function housekeepingTask(): BelongsTo { return $this->belongsTo(HousekeepingTask::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspected_by'); }
    public function guestServiceRequest() { return $this->hasOne(GuestServiceRequest::class); }
}
