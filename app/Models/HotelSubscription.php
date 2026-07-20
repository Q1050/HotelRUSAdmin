<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSubscription extends Model
{
    protected $fillable = ['hotel_id', 'plan_id', 'status', 'provider', 'provider_customer_id', 'provider_subscription_id', 'trial_started_at', 'trial_ends_at', 'current_period_ends_at', 'grace_ends_at', 'trial_reminders_sent'];

    protected function casts(): array
    {
        return ['trial_started_at' => 'datetime', 'trial_ends_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'trial_reminders_sent' => 'array'];
    }

    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function plan() { return $this->belongsTo(Plan::class); }

    public function accessEndsAt()
    {
        if ($this->status === 'trialing' && $this->trial_ends_at) {
            return $this->trial_ends_at->copy()->addDays(config('subscriptions.grace_days'));
        }

        return $this->status === 'grace' ? $this->grace_ends_at : null;
    }

    public function hasAccess(): bool
    {
        if ($this->status === 'active') return true;
        if (! in_array($this->status, ['trialing', 'grace'], true)) return false;

        return ! $this->accessEndsAt()?->isPast();
    }

    public function isReadOnly(): bool
    {
        return ! $this->hasAccess()
            || $this->status === 'grace'
            || ($this->status === 'trialing' && $this->trial_ends_at?->isPast());
    }

    public function lifecycleState(): string
    {
        if (! $this->hasAccess()) return 'expired';
        if ($this->isReadOnly()) return 'grace';
        return $this->status;
    }
}
