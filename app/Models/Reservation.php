<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'guest_id', 'room_id', 'reference', 'arrival_date', 'departure_date', 'guest_count', 'room_type', 'status', 'payment_status', 'total_amount', 'amount_paid', 'source', 'group_code', 'special_requests', 'created_by', 'no_show_at', 'no_show_fee', 'pricing_snapshot', 'tax_exempt', 'tax_exemption_reference', 'group_booking_id', 'corporate_account_id', 'negotiated_nightly_rate', 'billing_responsibility'];

    protected function casts(): array
    {
        return ['arrival_date' => 'date', 'departure_date' => 'date', 'total_amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'no_show_at' => 'datetime', 'no_show_fee' => 'decimal:2', 'pricing_snapshot' => 'array', 'tax_exempt' => 'boolean'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function preArrival()
    {
        return $this->hasOne(PreArrivalSubmission::class);
    }

    public function events()
    {
        return $this->hasMany(ReservationEvent::class)->latest('occurred_at');
    }

    public function groupBooking()
    {
        return $this->belongsTo(GroupBooking::class);
    }

    public function corporateAccount()
    {
        return $this->belongsTo(CorporateAccount::class);
    }
}
