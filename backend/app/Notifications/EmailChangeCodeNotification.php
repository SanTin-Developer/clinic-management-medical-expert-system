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
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Confirm your new clinic email address')
            ->view(['html' => 'emails.email-change-code', 'text' => 'emails.text.email-change-code'], [
                'newEmail' => $this->newEmail,
                'code' => $this->code,
                'expiresInMinutes' => (int) config('auth.email_change.expire_minutes', 15),
                'url' => $frontend.'/settings',
                'preheader' => 'Your code to confirm a new RoyalCare Clinic email address.',
            ]);
    }
}
