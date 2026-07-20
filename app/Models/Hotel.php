<?php
namespace App\Models;
use App\Support\Features;use Illuminate\Database\Eloquent\Model;
class Hotel extends Model{
protected $fillable=['name','slug','status','timezone','currency','settings'];
protected function casts():array{return['settings'=>'array'];}
public function subscription(){return$this->hasOne(HotelSubscription::class);}public function featureOverrides(){return$this->hasMany(HotelFeatureOverride::class);}public function integrations(){return$this->hasMany(HotelIntegration::class);}
public function limit(string$key):?int{$value=$this->subscription?->plan?->limits[$key]??null;return is_numeric($value)?(int)$value:null;}
public function hasFeature(string$feature):bool{if(!in_array($feature,Features::ALL,true)||$this->status!=='active')return false;$subscription=$this->subscription()->with('plan.features')->first();if(!$subscription?->hasAccess())return false;$override=$this->featureOverrides()->where('feature',$feature)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->first();if($override)return$override->enabled;return$subscription->plan->features->contains(fn($item)=>$item->feature===$feature&&$item->enabled);}
public function enabledFeatures():array{return collect(Features::ALL)->filter(fn($f)=>$this->hasFeature($f))->values()->all();}}
