<?php

namespace App\Services\Notifications;

use App\Models\Hotel;
use App\Models\NotificationRule;
use App\Models\User;
use Carbon\Carbon;

class NotificationRules
{
    public const EVENTS = [
        'booking.updated' => ['label' => 'Booking updates', 'channels' => ['fcm', 'email']],
        'access.updated' => ['label' => 'Room access updates', 'channels' => ['fcm']],
        'service.updated' => ['label' => 'Guest service updates', 'channels' => ['fcm', 'dashboard']],
        'checkout.reminder' => ['label' => 'Checkout reminders', 'channels' => ['fcm']],
        'housekeeping.created' => ['label' => 'Housekeeping requests', 'channels' => ['dashboard'], 'roles' => ['housekeeping', 'manager', 'super_admin']],
        'maintenance.created' => ['label' => 'Maintenance requests', 'channels' => ['dashboard'], 'roles' => ['maintenance', 'manager', 'super_admin']],
        'lock.alert' => ['label' => 'Lock and access alerts', 'channels' => ['dashboard', 'email'], 'roles' => ['manager', 'super_admin']],
        'security.alert' => ['label' => 'Security alerts', 'channels' => ['dashboard', 'email'], 'roles' => ['super_admin']],
        'financial.document' => ['label' => 'Financial documents', 'channels' => ['email'], 'roles' => ['manager', 'super_admin']],
    ];

    public function all(?Hotel $hotel = null): array
    {
        return collect(self::EVENTS)->map(function ($defaults, $key) use ($hotel) {
            $query = $hotel ? NotificationRule::withoutGlobalScopes()->where('hotel_id', $hotel->id) : NotificationRule::query();
            $rule = $query->where('event_key', $key)->first();

            return ['eventKey' => $key, 'label' => $rule?->label ?? $defaults['label'], 'enabled' => $rule?->enabled ?? true, 'channels' => $rule?->channels ?? $defaults['channels'], 'recipientRoles' => $rule?->recipient_roles ?? ($defaults['roles'] ?? []), 'deliveryMode' => $rule?->delivery_mode ?? 'immediate', 'digestTime' => $rule?->digest_time, 'quietStart' => $rule?->quiet_start, 'quietEnd' => $rule?->quiet_end, 'escalationMinutes' => $rule?->escalation_minutes, 'escalationRoles' => $rule?->escalation_roles ?? [], 'subjectTemplate' => $rule?->subject_template, 'bodyTemplate' => $rule?->body_template];
        })->values()->all();
    }

    public function rule(string $eventKey, ?Hotel $hotel = null): array
    {
        return collect($this->all($hotel))->firstWhere('eventKey', $eventKey) ?? ['eventKey' => $eventKey, 'label' => $eventKey, 'enabled' => true, 'channels' => ['fcm'], 'recipientRoles' => [], 'deliveryMode' => 'immediate', 'digestTime' => null, 'quietStart' => null, 'quietEnd' => null, 'escalationMinutes' => null, 'escalationRoles' => [], 'subjectTemplate' => null, 'bodyTemplate' => null];
    }

    public function nextDelivery(array $rule, Hotel $hotel): Carbon
    {
        $now = now($hotel->timezone);
        if ($rule['deliveryMode'] === 'digest' && $rule['digestTime']) {
            $at = Carbon::parse($now->toDateString().' '.$rule['digestTime'], $hotel->timezone);

            return $at->isFuture() ? $at : $at->addDay();
        }
        if ($rule['quietStart'] && $rule['quietEnd']) {
            $start = Carbon::parse($now->toDateString().' '.$rule['quietStart'], $hotel->timezone);
            $end = Carbon::parse($now->toDateString().' '.$rule['quietEnd'], $hotel->timezone);
            if ($end->lte($start)) {
                if ($now->lt($end)) {
                    $start->subDay();
                } else {
                    $end->addDay();
                }
            }
            if ($now->between($start, $end)) {
                return $end;
            }
        }

        return $now;
    }

    public function staffRecipients(string $eventKey, Hotel $hotel)
    {
        $rule = $this->rule($eventKey, $hotel);
        if (! $rule['enabled'] || ! array_intersect($rule['channels'], ['dashboard', 'email'])) {
            return collect();
        }

        return User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'active')->whereIn('role', $rule['recipientRoles'])->get();
    }
}
