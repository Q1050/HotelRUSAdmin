<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class HotelBackup extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'uuid', 'disk', 'path', 'status', 'size_bytes', 'checksum', 'manifest', 'error', 'completed_at', 'verified_at'];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'completed_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
