<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Folio extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'reservation_id', 'group_booking_id', 'guest_id', 'checkin_id', 'number', 'currency', 'status', 'charges_total', 'payments_total', 'refunds_total', 'balance', 'closed_at'];

    protected function casts(): array
    {
        return ['charges_total' => 'decimal:2', 'payments_total' => 'decimal:2', 'refunds_total' => 'decimal:2', 'balance' => 'decimal:2', 'closed_at' => 'datetime'];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function groupBooking()
    {
        return $this->belongsTo(GroupBooking::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function checkin()
    {
        return $this->belongsTo(Checkin::class);
    }

    public function items()
    {
        return $this->hasMany(FolioItem::class);
    }

    public function payments()
    {
        return $this->hasMany(FolioPayment::class);
    }

    public static function forReservation(Reservation $r): self
    {
        return static::firstOrCreate(['reservation_id' => $r->id], ['guest_id' => $r->guest_id, 'number' => 'FL-'.strtoupper(Str::random(10)), 'currency' => $r->hotel?->currency ?: 'USD']);
    }

    public static function forGroup(GroupBooking $group): self
    {
        return static::firstOrCreate(['group_booking_id' => $group->id], ['number' => 'GF-'.strtoupper(Str::random(10)), 'currency' => $group->hotel?->currency ?: 'USD']);
    }
}
