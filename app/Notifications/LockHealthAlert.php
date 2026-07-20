<?php

namespace App\Notifications;

use App\Models\LockDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LockHealthAlert extends Notification
{
    use Queueable;

    public function __construct(private LockDevice $device, private string $message) {}

    public function via(object $notifiable): array
    {
        $rule = app(\App\Services\Notifications\NotificationRules::class)->rule('lock.alert', $notifiable->hotel);
        if (! $rule['enabled']) {
            return [];
        }

        return collect($rule['channels'])->map(fn ($channel) => match ($channel) {
            'dashboard' => 'database', 'email' => 'mail', default => $channel
        })->intersect(['database', 'mail'])->values()->all();
    }

    public function toArray(object $notifiable): array
    {
        return ['lock_device_id' => $this->device->id, 'room_id' => $this->device->room_id, 'message' => $this->message, 'url' => route('dashboard.locks.index')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Lock health alert')->line($this->message)->action('Open lock management', route('dashboard.locks.index'));
    }
}
