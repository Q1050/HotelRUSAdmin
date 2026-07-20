<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationHealthCheck extends Model
{
    use Concerns\BelongsToHotel;

    protected $attributes = ['active' => true, 'status' => 'untested', 'consecutive_failures' => 0, 'failure_threshold' => 3, 'cooldown_minutes' => 5];

    protected $fillable = ['hotel_id', 'service', 'provider_key', 'label', 'active', 'status', 'consecutive_failures', 'failure_threshold', 'cooldown_minutes', 'last_response_ms', 'last_error', 'last_checked_at', 'last_success_at', 'last_failure_at', 'circuit_open_until'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_checked_at' => 'datetime', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'circuit_open_until' => 'datetime'];
    }
}
