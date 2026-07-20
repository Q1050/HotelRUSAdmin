<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestPasswordReset extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Reset your hotel guest password')
            ->line('Use this one-time code in the guest app to reset your password:')
            ->line($this->token)->line('This code expires in 30 minutes. If you did not request it, you can ignore this email.');
    }
}
