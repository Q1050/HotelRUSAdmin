<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class HotelFeatureOverride extends Model{protected $fillable=['hotel_id','feature','enabled','limits','expires_at'];protected function casts():array{return['enabled'=>'boolean','limits'=>'array','expires_at'=>'datetime'];}public function hotel(){return$this->belongsTo(Hotel::class);}}
