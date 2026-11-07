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
        $mapped = match ($this->status) {
            'suspended' => ['label' => 'Suspended', 'color' => 'red'],
            'disabled' => ['label' => 'Disabled', 'color' => 'red'],
            'active' => ['label' => 'Reactivated', 'color' => 'green'],
            'approved' => ['label' => 'Approved', 'color' => 'green'],
            'rejected' => ['label' => 'Rejected', 'color' => 'red'],
            default => ['label' => 'Updated', 'color' => 'slate'],
        };

        return (new MailMessage)
            ->subject('Your clinic account status has changed')
            ->view(['html' => 'emails.account-status-changed', 'text' => 'emails.text.account-status-changed'], [
                'name' => $notifiable->name,
                'statusLabel' => $mapped['label'],
                'statusColor' => $mapped['color'],
                'reason' => $this->reason !== null && $this->reason !== '' ? $this->reason : null,
                'url' => 'mailto:'.(string) config('mail.from.address'),
                'preheader' => 'Your RoyalCare Clinic account status has changed.',
            ]);
    }
}
