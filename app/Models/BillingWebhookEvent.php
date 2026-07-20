<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class BillingWebhookEvent extends Model{protected $fillable=['provider','provider_event_id','type','status','error','processed_at'];protected function casts():array{return['processed_at'=>'datetime'];}}
