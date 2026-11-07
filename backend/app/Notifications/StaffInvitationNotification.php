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

        $role = (string) $this->invitation->role;
        $article = in_array(strtolower(substr($role, 0, 1)), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';

        return (new MailMessage)
            ->subject('You have been invited to RoyalCare Clinic')
            ->view(['html' => 'emails.staff-invitation', 'text' => 'emails.text.staff-invitation'], [
                'name' => $this->invitation->name,
                'article' => $article,
                'role' => $role,
                'expireHours' => (int) config('auth.invitation.expire_hours', 72),
                'url' => $url,
                'preheader' => 'You have been invited to join the RoyalCare Clinic staff portal.',
            ]);
    }
}
