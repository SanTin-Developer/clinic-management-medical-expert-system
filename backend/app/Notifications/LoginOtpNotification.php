<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
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
            ->subject('Your clinic login verification code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A sign-in attempt was made from a new device. Use the one-time code below to continue:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in '.$this->expiresInMinutes.' minutes and can only be used once.')
            ->line('If you did not attempt to sign in, change your password immediately.');
    }
}
