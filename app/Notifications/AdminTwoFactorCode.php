<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;use Illuminate\Notifications\Messages\MailMessage;use Illuminate\Notifications\Notification;
class AdminTwoFactorCode extends Notification{use Queueable;public function __construct(private string $code){}public function via(object $notifiable):array{return['mail'];}public function toMail(object $notifiable):MailMessage{return(new MailMessage)->subject('HotelKey sign-in verification code')->line('Your HotelKey administrator verification code is:')->line($this->code)->line('This code expires in 10 minutes.');}}
