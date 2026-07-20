<?php

namespace App\Notifications;

use App\Models\MaintenanceWorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceAlert extends Notification
{
    use Queueable;

    public function __construct(private MaintenanceWorkOrder $order, private string $message) {}

    public function via(object $notifiable): array
    {
        $rule = app(\App\Services\Notifications\NotificationRules::class)->rule('maintenance.created', $notifiable->hotel);
        if (! $rule['enabled']) {
            return [];
        }

        return collect($rule['channels'])->map(fn ($channel) => match ($channel) {
            'dashboard' => 'database', 'email' => 'mail', default => $channel
        })->intersect(['database', 'mail'])->values()->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Maintenance notification')->line($this->message)->action('Open maintenance', route('dashboard.maintenance.index'));
    }

    public function toArray(object $notifiable): array
    {
        return ['work_order_id' => $this->order->id, 'room_id' => $this->order->room_id, 'message' => $this->message, 'url' => route('dashboard.maintenance.index')];
    }
}
