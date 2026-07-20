<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LockSyncAttempt extends Model{protected $fillable=['lock_device_id','operation','status','attempts','error','payload','next_retry_at','completed_at'];protected function casts():array{return['payload'=>'array','next_retry_at'=>'datetime','completed_at'=>'datetime'];}public function device():BelongsTo{return $this->belongsTo(LockDevice::class,'lock_device_id');}}
