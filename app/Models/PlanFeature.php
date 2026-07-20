<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class PlanFeature extends Model{public $timestamps=false;protected $fillable=['plan_id','feature','enabled','limits'];protected function casts():array{return['enabled'=>'boolean','limits'=>'array'];}public function plan(){return$this->belongsTo(Plan::class);}}
