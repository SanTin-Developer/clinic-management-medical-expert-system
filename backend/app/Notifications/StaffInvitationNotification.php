<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly string $token,
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
        $path = (string) config('auth.invitation.frontend_path', '/accept-invitation');
        $url = $frontend.$path.'?token='.urlencode($this->token);

        return (new MailMessage)
            ->subject('You have been invited to RoyalCare Clinic')
            ->greeting('Hello '.$this->invitation->name.',')
            ->line('An administrator has invited you to join the RoyalCare Clinic staff portal as a '.$this->invitation->role.'.')
            ->action('Accept your invitation', $url)
            ->line('For your security the invitation link expires in '.(int) config('auth.invitation.expire_hours', 72).' hours.')
            ->line('When you accept you will be asked to set your own password, enable two-factor authentication and choose a username.')
            ->line('If you were not expecting this invitation you can safely ignore this email.');
    }
}
