<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class Plan extends Model{protected $fillable=['key','name','active','limits','stripe_price_id','monthly_price'];protected function casts():array{return['active'=>'boolean','limits'=>'array'];}public function features(){return$this->hasMany(PlanFeature::class);}public function subscriptions(){return$this->hasMany(HotelSubscription::class);}}
