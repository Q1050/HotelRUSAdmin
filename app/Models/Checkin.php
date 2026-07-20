<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    use HasFactory, Concerns\BelongsToHotel;

    protected $fillable = [
        'guest_id',
        'reservation_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'payment_status',
        'booking_reference',
        'is_active',
        'access_key_hash',
        'access_key_expires_at',
        'access_suspended_at',
        'access_suspension_reason',
        'hotel_id',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'is_active' => 'boolean',
            'access_key_expires_at' => 'datetime',
            'access_suspended_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
