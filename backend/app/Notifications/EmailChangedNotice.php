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
        return (new MailMessage)
            ->subject('Your clinic account email address was changed')
            ->greeting('Hello,')
            ->line('The email address for a RoyalCare Clinic account was changed from '.$this->oldEmail.' to '.$this->newEmail.'.')
            ->line('For security, all sessions and trusted devices were signed out.')
            ->line('If you did not make this change, contact your administrator immediately.');
    }
}
