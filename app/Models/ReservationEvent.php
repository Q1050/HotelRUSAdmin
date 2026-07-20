<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class ReservationEvent extends Model{use Concerns\BelongsToHotel;protected $fillable=['hotel_id','reservation_id','actor_id','type','description','metadata','occurred_at'];protected function casts():array{return['metadata'=>'array','occurred_at'=>'datetime'];}public function actor(){return$this->belongsTo(User::class,'actor_id');}}
