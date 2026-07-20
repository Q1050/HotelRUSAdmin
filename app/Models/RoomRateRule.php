<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomRateRule extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'room_type', 'start_date', 'end_date', 'nightly_rate', 'minimum_stay', 'closed_to_arrival', 'notes', 'created_by'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'nightly_rate' => 'decimal:2', 'closed_to_arrival' => 'boolean']; }
}
