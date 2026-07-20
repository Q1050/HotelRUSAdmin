<?php
namespace App\Models;use App\Models\Concerns\BelongsToHotel;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditEvent extends Model{use BelongsToHotel;public $timestamps=false;protected $fillable=['hotel_id','actor_id','action','category','severity','subject_type','subject_id','description','reason','metadata','ip_address','user_agent','occurred_at'];protected function casts():array{return['metadata'=>'array','occurred_at'=>'datetime'];}public function actor():BelongsTo{return$this->belongsTo(User::class,'actor_id');}}
