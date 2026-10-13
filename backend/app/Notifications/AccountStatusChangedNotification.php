<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
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
        $message = (new MailMessage)
            ->subject('Your clinic account status has changed')
            ->greeting('Hello '.$notifiable->name.',');

        $message->line(match ($this->status) {
            'suspended' => 'Your RoyalCare Clinic account has been suspended.',
            'disabled' => 'Your RoyalCare Clinic account has been disabled.',
            'active' => 'Your RoyalCare Clinic account has been reactivated.',
            default => 'Your RoyalCare Clinic account status has changed.',
        });

        if ($this->reason !== null && $this->reason !== '') {
            $message->line('Reason: '.$this->reason);
        }

        return $message
            ->line('If you believe this is a mistake, contact your administrator.');
    }
}
