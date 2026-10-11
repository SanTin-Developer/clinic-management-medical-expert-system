<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your clinic password was changed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('This is a security notice that the password for your account was just changed.')
            ->line('All other sessions and trusted devices have been signed out.')
            ->line('If you did not make this change, contact your administrator immediately.');
    }
}
