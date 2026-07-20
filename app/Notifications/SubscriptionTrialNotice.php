<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionTrialNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $message, private string $state) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => route('dashboard'), 'type' => 'subscription_'.$this->state];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('HotelCheckin subscription update')
            ->line($this->message)
            ->line('Contact your HotelCheckin account representative to activate or update your subscription.');
    }
}
