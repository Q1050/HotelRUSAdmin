<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestPrivacyRequest extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id','guest_id','type','status','guest_reason','reviewed_by','review_notes','reviewed_at','completed_at'];
    protected function casts(): array { return ['reviewed_at'=>'datetime','completed_at'=>'datetime']; }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
