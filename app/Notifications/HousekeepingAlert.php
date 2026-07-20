<?php

namespace App\Notifications;

use App\Models\HousekeepingTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HousekeepingAlert extends Notification
{
    use Queueable;

    public function __construct(private HousekeepingTask $task, private string $message) {}

    public function via(object $notifiable): array
    {
        $rule = app(\App\Services\Notifications\NotificationRules::class)->rule('housekeeping.created', $notifiable->hotel);
        if (! $rule['enabled']) {
            return [];
        }

        return collect($rule['channels'])->map(fn ($channel) => match ($channel) {
            'dashboard' => 'database', 'email' => 'mail', default => $channel
        })->intersect(['database', 'mail'])->values()->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Housekeeping notification')->line($this->message)->action('Open housekeeping', route('dashboard.housekeeping.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'room_id' => $this->task->room_id,
            'room_number' => $this->task->room?->number,
            'message' => $this->message,
            'url' => route('dashboard.housekeeping.index'),
        ];
    }
}
