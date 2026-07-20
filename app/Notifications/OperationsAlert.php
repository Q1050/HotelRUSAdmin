<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationsAlert extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $message,
        private string $severity = 'warning',
        private bool $email = true,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof \Illuminate\Database\Eloquent\Model ? ['database'] : [];

        if ($this->email && filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Open operations center', route('dashboard.security.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'title' => $this->title,
            'severity' => $this->severity,
            'url' => route('dashboard.security.index'),
        ];
    }
}
