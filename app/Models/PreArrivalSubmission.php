<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreArrivalSubmission extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id','reservation_id','guest_id','status','id_type','id_number','id_document_front','id_document_back','estimated_arrival_time','guest_notes','policy_accepted','consented_at','consent_ip','reviewed_by','reviewed_at','review_notes'];
    protected function casts(): array { return ['policy_accepted'=>'boolean','consented_at'=>'datetime','reviewed_at'=>'datetime']; }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
