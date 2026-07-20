<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HousekeepingTask extends Model { use Concerns\BelongsToHotel;
 protected $fillable=['hotel_id','room_id','checkin_id','task_type','assigned_to','assigned_by','assigned_at','reassignment_reason','status','priority','due_at','notes','checklist','started_at','completed_at','inspected_at','inspected_by','maintenance_notes'];
 protected function casts():array{return['assigned_at'=>'datetime','due_at'=>'datetime','checklist'=>'array','started_at'=>'datetime','completed_at'=>'datetime','inspected_at'=>'datetime'];}
 public function room():BelongsTo{return $this->belongsTo(Room::class);} public function assignee():BelongsTo{return $this->belongsTo(User::class,'assigned_to');} public function assigner():BelongsTo{return $this->belongsTo(User::class,'assigned_by');}
 public function guestServiceRequest(){return$this->hasOne(GuestServiceRequest::class);}
}
