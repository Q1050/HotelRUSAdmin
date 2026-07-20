<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class FolioPayment extends Model{use Concerns\BelongsToHotel;protected $fillable=['hotel_id','folio_id','parent_payment_id','type','method','provider','external_reference','idempotency_key','amount','status','notes','processed_at','recorded_by'];protected function casts():array{return['amount'=>'decimal:2','processed_at'=>'datetime'];}public function folio(){return$this->belongsTo(Folio::class);}public function parent(){return$this->belongsTo(self::class,'parent_payment_id');}}
