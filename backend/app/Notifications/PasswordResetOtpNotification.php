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
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Your clinic password reset code')
            ->view(['html' => 'emails.password-reset', 'text' => 'emails.text.password-reset'], [
                'name' => $notifiable->name,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'url' => $frontend.'/forgot-password',
                'preheader' => 'Your one-time password reset code for RoyalCare Clinic.',
            ]);
    }
}
