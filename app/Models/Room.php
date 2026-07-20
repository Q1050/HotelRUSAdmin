<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Room extends Model
{
    use HasFactory, Concerns\BelongsToHotel;

    protected static function booted(): void
    {
        static::creating(fn (Room $room) => $room->access_marker ??= (string) Str::uuid());
    }

    protected $fillable = [
        'number',
        'type',
        'floor',
        'status',
        'lock_status',
        'price',
        'last_cleaned_at',
        'access_marker',
        'hotel_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'last_cleaned_at' => 'datetime',
        ];
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RoomEvent::class)->latest('occurred_at')->latest('id');
    }

    public function lockDevice(): HasOne { return $this->hasOne(LockDevice::class); }

    public function latestHousekeepingTask(): HasOne
    {
        return $this->hasOne(HousekeepingTask::class)->latestOfMany();
    }

    public function maintenanceWorkOrders(): HasMany { return $this->hasMany(MaintenanceWorkOrder::class); }
    public function latestMaintenanceWorkOrder(): HasOne { return $this->hasOne(MaintenanceWorkOrder::class)->latestOfMany(); }
}
