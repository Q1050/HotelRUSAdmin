<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileNotification extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'guest_id', 'category', 'event_key', 'title', 'body', 'data', 'channels', 'scheduled_for', 'delivery_status', 'device_count', 'delivery_error', 'sent_at', 'read_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'channels' => 'array', 'scheduled_for' => 'datetime', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
