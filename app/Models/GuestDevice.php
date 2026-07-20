<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GuestDevice extends Model{protected $fillable=['guest_id','device_id','name','platform','push_token','ip_address','last_seen_at','revoked_at'];protected function casts():array{return['last_seen_at'=>'datetime','revoked_at'=>'datetime'];}public function guest():BelongsTo{return $this->belongsTo(Guest::class);}}
