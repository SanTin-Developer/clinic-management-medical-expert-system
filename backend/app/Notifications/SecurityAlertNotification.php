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
        $frontend = rtrim((string) config('services.frontend.url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('Important security alert on your clinic account')
            ->view(['html' => 'emails.security-alert', 'text' => 'emails.text.security-alert'], [
                'name' => $notifiable->name,
                'eventDescription' => $this->describe(),
                'when' => $when,
                'ipAddress' => $this->event->ip_address,
                'url' => $frontend.'/settings',
                'preheader' => 'A security event was recorded on your RoyalCare Clinic account.',
            ]);
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
