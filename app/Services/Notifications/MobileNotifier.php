<?php

namespace App\Services\Notifications;

use App\Jobs\DeliverMobileNotification;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MobileNotification;
use App\Notifications\GuestEventEmail;
use RuntimeException;

class MobileNotifier
{
    public function __construct(private FcmClient $fcm, private NotificationRules $rules) {}

    public function send(Guest $guest, string $category, string $title, string $body, array $data = []): MobileNotification
    {
        $eventKey = $data['event_key'] ?? match ($category) {
            'booking' => 'booking.updated', 'access' => 'access.updated', 'service' => 'service.updated', 'checkout' => 'checkout.reminder', default => $category.'.updated'
        };
        $rule = $this->rules->rule($eventKey, $guest->hotel);
        $channels = $rule['channels'];
        $subject = $this->render($rule['subjectTemplate'] ?: $title, compact('title', 'body'));
        $message = $this->render($rule['bodyTemplate'] ?: $body, compact('title', 'body'));
        $deliveryAt = $this->rules->nextDelivery($rule, $guest->hotel);
        $notification = MobileNotification::create(compact('category', 'title', 'body', 'data', 'channels') + ['guest_id' => $guest->id, 'event_key' => $eventKey, 'scheduled_for' => $deliveryAt]);
        if (! $rule['enabled']) {
            return tap($notification)->update(['delivery_status' => 'rule_disabled']);
        }
        $preference = $guest->notificationPreference()->firstOrCreate([]);
        $field = match ($category) {
            'booking' => 'booking_updates', 'access' => 'access_updates', 'service' => 'service_updates',
            'checkout' => 'checkout_reminders', default => 'marketing'
        };
        if (! $preference->{$field}) {
            return tap($notification)->update(['delivery_status' => 'disabled']);
        }
        if (in_array('email', $channels) && filled($guest->email)) {
            $guest->notify((new GuestEventEmail($subject, $message))->delay($deliveryAt));
        }
        if (! in_array('fcm', $channels)) {
            return tap($notification)->update(['delivery_status' => in_array('email', $channels) ? 'email_queued' : 'channel_disabled']);
        }
        $devices = $guest->devices()->whereNull('revoked_at')->whereNotNull('push_token')->count();
        if (! $devices) {
            return tap($notification)->update(['delivery_status' => 'no_devices']);
        }
        if (! $this->fcm->configured($guest->hotel)) {
            return tap($notification)->update(['delivery_status' => 'configuration_required', 'device_count' => $devices]);
        }

        $notification->update(['delivery_status' => $deliveryAt->isFuture() ? 'scheduled' : 'queued', 'device_count' => $devices]);
        DeliverMobileNotification::dispatch($notification->id)->delay($deliveryAt)->afterCommit();

        return $notification->fresh();
    }

    public function deliver(int $notificationId): void
    {
        $notification = MobileNotification::withoutGlobalScopes()->with('guest.hotel')->findOrFail($notificationId);
        $guest = $notification->guest;
        app()->instance('currentHotel', $guest->hotel);
        $devices = $guest->devices()->whereNull('revoked_at')->whereNotNull('push_token')->get();
        if ($devices->isEmpty()) {
            $notification->update(['delivery_status' => 'no_devices', 'device_count' => 0]);

            return;
        }

        $sent = 0;
        $errors = [];
        foreach ($devices as $device) {
            try {
                $this->fcm->send($device->push_token, $notification->title, $notification->body,
                    ($notification->data ?? []) + ['notification_id' => $notification->id, 'category' => $notification->category], $guest->hotel);
                $sent++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                if (str_contains($e->getMessage(), 'UNREGISTERED')) {
                    $device->update(['push_token' => null]);
                }
            }
        }
        $notification->update(['delivery_status' => $sent === $devices->count() ? 'sent' : ($sent ? 'partial' : 'retrying'),
            'device_count' => $sent, 'delivery_error' => $errors ? substr(implode('; ', $errors), 0, 2000) : null, 'sent_at' => $sent ? now() : null]);
        if (! $sent && $errors) {
            throw new RuntimeException($errors[0]);
        }
    }

    public function configured(?Hotel $hotel = null): bool
    {
        return $this->fcm->configured($hotel);
    }

    private function render(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}
