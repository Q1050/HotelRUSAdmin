<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class FoodItem extends Model{use Concerns\BelongsToHotel;protected $fillable=['hotel_id','food_category_id','name','description','price','tax_rate','dietary_tags','allergens','available'];protected function casts():array{return['price'=>'decimal:2','tax_rate'=>'decimal:2','dietary_tags'=>'array','available'=>'boolean'];}public function category(){return$this->belongsTo(FoodCategory::class,'food_category_id');}}
