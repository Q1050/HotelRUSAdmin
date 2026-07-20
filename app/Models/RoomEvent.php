<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomEvent extends Model
{
    use Concerns\BelongsToHotel;
    protected $fillable = ['hotel_id','room_id', 'guest_id', 'checkin_id', 'actor_id', 'event_type', 'description', 'metadata', 'occurred_at'];
    protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function checkin(): BelongsTo { return $this->belongsTo(Checkin::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
