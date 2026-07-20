<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NightAudit extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'business_date', 'status', 'charges_posted', 'room_revenue', 'payments', 'refunds', 'outstanding_balance', 'occupied_rooms', 'arrivals', 'departures', 'no_shows', 'exceptions', 'override_reason', 'closed_by', 'closed_at', 'reopened_by', 'reopen_reason', 'reopened_at'];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'room_revenue' => 'decimal:2', 'payments' => 'decimal:2', 'refunds' => 'decimal:2', 'outstanding_balance' => 'decimal:2', 'exceptions' => 'array', 'closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function events()
    {
        return $this->hasMany(NightAuditEvent::class);
    }
}
