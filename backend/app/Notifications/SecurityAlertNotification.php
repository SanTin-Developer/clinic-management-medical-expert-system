<?php

namespace App\Notifications;

use App\Models\SecurityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies an account owner that a critical security event occurred on their
 * account (for example, the account was locked after repeated failed sign-ins).
 * The message never includes secrets; it only summarises the logged event.
 */
class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SecurityEvent $event,
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
        $when = optional($this->event->created_at)->toDayDateTimeString() ?? 'an unknown time';

        $message = (new MailMessage)
            ->subject('Important security alert on your clinic account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We detected an important security event on your RoyalCare Clinic account.');

        $message->line('Event: '.$this->describe());
        $message->line('When: '.$when);

        if ($this->event->ip_address !== null) {
            $message->line('IP address: '.$this->event->ip_address);
        }

        return $message
            ->line('If this was not you, please change your password and contact your administrator immediately.');
    }

    private function describe(): string
    {
        return match ($this->event->event) {
            'login.locked' => 'Your account was temporarily locked after repeated failed sign-in attempts.',
            'login.blocked' => 'A sign-in attempt was blocked because your account is not active.',
            default => 'A security-sensitive action was recorded on your account.',
        };
    }
}
