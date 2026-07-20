<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class LockWebhookEvent extends Model{protected $fillable=['provider_key','external_event_id','event_type','payload','status','error','processed_at'];protected function casts():array{return['payload'=>'array','processed_at'=>'datetime'];}}
