<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminRecoveryNotice extends Notification
{
    use Queueable;

    public function __construct(private string $hotel, private string $message) {}

    public function via(object $n): array
    {
        return filled($n->routeNotificationFor('mail')) ? ['mail'] : [];
    }

    public function toMail(object $n): MailMessage
    {
        return (new MailMessage)->subject("{$this->hotel} administrator recovery")->line($this->message)->line('Review staff access, rotate relevant credentials, and confirm two-factor authentication immediately.');
    }
}
