<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'base_role', 'permissions', 'description'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
