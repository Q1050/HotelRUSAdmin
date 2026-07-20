<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReservationClaimToken extends Model{protected $fillable=['reservation_id','guest_id','code_hash','attempts','expires_at','used_at'];protected function casts():array{return['expires_at'=>'datetime','used_at'=>'datetime'];}public function reservation():BelongsTo{return $this->belongsTo(Reservation::class);}public function guest():BelongsTo{return $this->belongsTo(Guest::class);}}
