<?php
namespace App\Models\Concerns;use App\Models\Hotel;use Illuminate\Database\Eloquent\Builder;
trait BelongsToHotel{protected static function bootBelongsToHotel():void{static::addGlobalScope('hotel',function(Builder$q){if(app()->bound('currentHotel'))$q->where($q->qualifyColumn('hotel_id'),app('currentHotel')->id);});static::creating(function($model){if(!$model->hotel_id)$model->hotel_id=app()->bound('currentHotel')?app('currentHotel')->id:Hotel::query()->value('id');});}public function hotel(){return$this->belongsTo(Hotel::class);}}
