<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerts website administrators when a visitor submits the public Contact form.
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $contactMessageId,
        public string $senderName,
        public string $senderEmail,
        public ?string $subject,
        public string $preview,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'contact_message',
            'contact_message_id' => $this->contactMessageId,
            'sender_name' => $this->senderName,
            'sender_email' => $this->senderEmail,
            'subject' => $this->subject,
            'preview' => $this->preview,
            'url' => '/website/contact-messages',
        ];
    }
}
