<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security alert delivered on-demand to the previous email address so the
 * original owner is told their address was removed from the account.
 */
class EmailChangedNotice extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $oldEmail,
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
            ->subject('Your clinic account email address was changed')
            ->view(['html' => 'emails.email-changed', 'text' => 'emails.text.email-changed'], [
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'url' => $frontend.'/settings',
                'preheader' => 'The email address on your RoyalCare Clinic account was changed.',
            ]);
    }
}
