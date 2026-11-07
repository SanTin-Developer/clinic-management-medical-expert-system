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
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Your clinic login verification code')
            ->view(['html' => 'emails.login-otp', 'text' => 'emails.text.login-otp'], [
                'name' => $notifiable->name,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'url' => $frontend.'/login/verify',
                'preheader' => 'Your one-time sign-in code for RoyalCare Clinic.',
            ]);
    }
}
