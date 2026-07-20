<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBlock extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'room_id', 'room_type', 'name', 'group_code', 'start_date', 'end_date', 'quantity', 'status', 'notes', 'created_by'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date']; }
    public function room() { return $this->belongsTo(Room::class); }
}
