<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class FoodOrderItem extends Model{use Concerns\BelongsToHotel;protected $fillable=['hotel_id','food_order_id','food_item_id','name','quantity','unit_price','tax_amount','total','modifiers'];protected function casts():array{return['unit_price'=>'decimal:2','tax_amount'=>'decimal:2','total'=>'decimal:2'];}public function order(){return$this->belongsTo(FoodOrder::class,'food_order_id');}}
