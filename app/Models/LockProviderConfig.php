<?php
namespace App\Models;use App\Models\Concerns\BelongsToHotel;use Illuminate\Database\Eloquent\Model;
class LockProviderConfig extends Model{use BelongsToHotel;protected $fillable=['hotel_id','key','name','driver','base_url','credentials','webhook_secret','active','connection_status','last_error','last_tested_at'];protected $hidden=['credentials','webhook_secret'];protected function casts():array{return['credentials'=>'encrypted:array','webhook_secret'=>'encrypted','active'=>'boolean','last_tested_at'=>'datetime'];}}
