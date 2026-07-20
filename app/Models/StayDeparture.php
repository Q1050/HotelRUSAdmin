<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StayDeparture extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'checkin_id', 'reservation_id', 'guest_id', 'room_id', 'type', 'reason', 'financial_resolution', 'refund_amount', 'security_involved', 'do_not_rent', 'notes', 'departed_at', 'performed_by','financial_reviewed_at','financial_reviewed_by','financial_review_notes'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2', 'security_involved' => 'boolean', 'do_not_rent' => 'boolean', 'departed_at' => 'datetime','financial_reviewed_at'=>'datetime'];
    }
    public function guest(){return $this->belongsTo(Guest::class);}
    public function room(){return $this->belongsTo(Room::class);}
    public function reservation(){return $this->belongsTo(Reservation::class);}
}
