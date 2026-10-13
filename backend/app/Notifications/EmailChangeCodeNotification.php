<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered on-demand to the requested new email address. It never assumes the
 * notifiable exposes a name (on-demand notifiables are anonymous).
 */
class EmailChangeCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $newEmail,
    ) {}

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
            ->subject('Confirm your new clinic email address')
            ->greeting('Hello,')
            ->line('Use the code below to confirm '.$this->newEmail.' as the new email address for your RoyalCare Clinic account:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in '.(int) config('auth.email_change.expire_minutes', 15).' minutes.')
            ->line('If you did not request this change you can ignore this email.');
    }
}
