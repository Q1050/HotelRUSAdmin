<?php
namespace App\Models;
use App\Support\Features;use Illuminate\Database\Eloquent\Model;
class Hotel extends Model{
protected $fillable=['organization_id','name','slug','status','timezone','currency','settings','organization_inheritance'];
protected function casts():array{return['settings'=>'array','organization_inheritance'=>'array'];}
public function organization(){return$this->belongsTo(Organization::class);}
public function inherits(string$key):bool{return(bool)($this->organization_id&&($this->organization_inheritance[$key]??false));}
public function subscription(){return$this->hasOne(HotelSubscription::class);}public function featureOverrides(){return$this->hasMany(HotelFeatureOverride::class);}public function integrations(){return$this->hasMany(HotelIntegration::class);}
public function limit(string$key):?int{$value=$this->subscription?->plan?->limits[$key]??null;return is_numeric($value)?(int)$value:null;}
public function hasFeature(string$feature):bool{if(!in_array($feature,Features::ALL,true)||$this->status!=='active')return false;$subscription=$this->subscription()->with('plan.features')->first();if(!$subscription?->hasAccess())return false;$override=$this->featureOverrides()->where('feature',$feature)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->first();if($override)return$override->enabled;return$subscription->plan->features->contains(fn($item)=>$item->feature===$feature&&$item->enabled);}
public function enabledFeatures():array{return collect(Features::ALL)->filter(fn($f)=>$this->hasFeature($f))->values()->all();}}
