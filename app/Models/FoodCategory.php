<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class FoodCategory extends Model{use Concerns\BelongsToHotel;protected $fillable=['hotel_id','name','sort_order','active'];protected function casts():array{return['active'=>'boolean'];}public function items(){return$this->hasMany(FoodItem::class);}}
