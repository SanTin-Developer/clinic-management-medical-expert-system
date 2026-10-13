<?php

namespace App\Jobs;

use App\Models\ConversationMessage;
use App\Models\TelegramDeliveryLog;
use App\Notifications\TelegramDeliveryFailedNotification;
use App\Services\Telegram\TelegramClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(
        public int|string|null $chatId,
        public string $text,
        public ?int $userId = null,
        public string $eventType = 'generic',
        public ?string $subject = null,
        public ?string $idempotencyKey = null,
        public ?int $conversationId = null,
        public ?int $conversationMessageId = null,
    ) {}

    public function handle(TelegramClient $client): void
    {
        if ($this->chatId === null || ! $client->isConfigured()) {
            return;
        }

        $log = $this->resolveLog();

        if ($log->status === TelegramDeliveryLog::STATUS_SENT) {
            return;
        }

        try {
            $result = $client->sendMessage($this->chatId, $this->text);

            $log->update([
                'status' => TelegramDeliveryLog::STATUS_SENT,
                'error' => null,
                'attempts' => $log->attempts + 1,
                'telegram_message_id' => $result['result']['message_id'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => TelegramDeliveryLog::STATUS_FAILED,
                'error' => Str::limit($exception->getMessage(), 255),
                'attempts' => $log->attempts + 1,
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->idempotencyKey === null) {
            return;
        }

        TelegramDeliveryLog::query()
            ->where('idempotency_key', $this->idempotencyKey)
            ->update([
                'status' => TelegramDeliveryLog::STATUS_FAILED,
                'error' => Str::limit($exception?->getMessage() ?? 'Delivery failed.', 255),
            ]);

        $this->notifySender();
    }

    /**
     * Let the message author know their Telegram copy never went out, without
     * revealing any content. The author is the sender of the conversation
     * message; the recipient (if any) is `$this->userId`.
     */
    private function notifySender(): void
    {
        if ($this->conversationMessageId === null || $this->conversationId === null) {
            return;
        }

        $message = ConversationMessage::query()
            ->with('sender')
            ->find($this->conversationMessageId);

        $sender = $message?->sender;

        if ($sender === null || $sender->id === $this->userId) {
            return;
        }

        $sender->notify(new TelegramDeliveryFailedNotification(
            $this->conversationId,
            $this->conversationMessageId,
        ));
    }

    private function resolveLog(): TelegramDeliveryLog
    {
        $attributes = [
            'user_id' => $this->userId,
            'telegram_chat_id' => $this->chatId,
            'conversation_id' => $this->conversationId,
            'conversation_message_id' => $this->conversationMessageId,
            'event_type' => $this->eventType,
            'subject' => $this->subject,
            'idempotency_key' => $this->idempotencyKey,
        ];

        if ($this->idempotencyKey !== null) {
            return TelegramDeliveryLog::firstOrCreate(
                ['idempotency_key' => $this->idempotencyKey],
                $attributes,
            );
        }

        return TelegramDeliveryLog::create($attributes);
    }
}
