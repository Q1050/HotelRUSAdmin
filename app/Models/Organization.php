<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'settings', 'credentials'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'credentials' => 'encrypted:array'];
    }

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
