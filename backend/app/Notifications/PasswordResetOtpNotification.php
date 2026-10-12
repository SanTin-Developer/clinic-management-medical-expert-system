<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
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
            ->subject('Your clinic password reset code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your account. Use the one-time code below to continue:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in '.$this->expiresInMinutes.' minutes and can only be used once.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }
}
