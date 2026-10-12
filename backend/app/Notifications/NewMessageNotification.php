<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversationId,
        public ?string $conversationTitle,
        public int $messageId,
        public string $senderName,
        public string $preview,
        public bool $isGroup = false,
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
            'kind' => 'new_message',
            'conversation_id' => $this->conversationId,
            'conversation_title' => $this->conversationTitle,
            'message_id' => $this->messageId,
            'sender_name' => $this->senderName,
            'preview' => $this->preview,
            'is_group' => $this->isGroup,
            'url' => '/chat/'.$this->conversationId,
        ];
    }
}
