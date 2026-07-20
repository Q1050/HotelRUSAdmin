<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class HotelIntegration extends Model{protected $fillable=['hotel_id','type','active','configuration','connection_status','last_error','last_tested_at'];protected $hidden=['configuration'];protected function casts():array{return['active'=>'boolean','configuration'=>'encrypted:array','last_tested_at'=>'datetime'];}public function hotel(){return$this->belongsTo(Hotel::class);}}
