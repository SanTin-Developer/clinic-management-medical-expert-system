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
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Your clinic password was changed')
            ->view(['html' => 'emails.password-changed', 'text' => 'emails.text.password-changed'], [
                'name' => $notifiable->name,
                'url' => $frontend.'/settings',
                'preheader' => 'Your RoyalCare Clinic password was changed.',
            ]);
    }
}
