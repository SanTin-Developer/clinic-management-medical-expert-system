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
use Illuminate\Support\Facades\Storage;
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
        public ?string $photoDisk = null,
        public ?string $photoPath = null,
        public ?string $photoUrl = null,
        public ?string $documentDisk = null,
        public ?string $documentPath = null,
        public ?string $documentUrl = null,
        public ?string $documentFilename = null,
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
            $result = $this->deliver($client);

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

    /**
     * @return array<string, mixed>
     */
    private function deliver(TelegramClient $client): array
    {
        $caption = Str::limit($this->text, 1024, '');

        if ($this->photoUrl !== null) {
            try {
                $filename = $this->documentFilename ?: basename((string) parse_url($this->photoUrl, PHP_URL_PATH));
                if ($filename === '') {
                    $filename = null;
                }

                return $client->sendPhoto($this->chatId, $this->photoUrl, $caption, $filename);
            } catch (Throwable) {
                // fall through to try other methods/text
            }
        }

        if ($this->photoPath !== null) {
            $disk = Storage::disk($this->photoDisk ?? 'public');
            if ($disk->exists($this->photoPath)) {
                $stream = $disk->readStream($this->photoPath);
                if ($stream !== null) {
                    try {
                        return $client->sendPhoto(
                            $this->chatId,
                            $stream,
                            $caption,
                            $this->documentFilename ?: basename($this->photoPath),
                        );
                    } catch (Throwable) {
                        // fall through
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
            }
        }

        if ($this->documentUrl !== null) {
            try {
                $filename = $this->documentFilename ?: basename((string) parse_url($this->documentUrl, PHP_URL_PATH));
                if ($filename === '') {
                    $filename = null;
                }
                if ($this->looksLikeVideo($filename) || $this->looksLikeVideo($this->documentUrl)) {
                    try {
                        return $client->sendVideo($this->chatId, $this->documentUrl, $caption, $filename);
                    } catch (Throwable) {
                    }
                }

                return $client->sendDocument($this->chatId, $this->documentUrl, $caption, $filename);
            } catch (Throwable) {
                // fall through
            }
        }

        if ($this->documentPath !== null) {
            $disk = Storage::disk($this->documentDisk ?? 'local');
            if ($disk->exists($this->documentPath)) {
                $stream = $disk->readStream($this->documentPath);
                if ($stream !== null) {
                    try {
                        $filename = $this->documentFilename ?: basename($this->documentPath);
                        if ($this->looksLikeVideo($filename)) {
                            try {
                                $stream2 = $disk->readStream($this->documentPath);
                                if (is_resource($stream2)) {
                                    try {
                                        return $client->sendVideo($this->chatId, $stream2, $caption, $filename);
                                    } finally {
                                        if (is_resource($stream2)) {
                                            fclose($stream2);
                                        }
                                    }
                                }
                            } catch (Throwable) {
                            }
                        }

                        return $client->sendDocument(
                            $this->chatId,
                            $stream,
                            $caption,
                            $filename,
                        );
                    } catch (Throwable) {
                        // fall through
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
            }
        }

        return $client->sendMessage($this->chatId, $this->text);
    }

    private function looksLikeVideo(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            return true;
        }

        if (str_contains(strtolower($value), '/video/') || str_contains(strtolower($value), 'video')) {
            $ext = strtolower((string) parse_url($value, PHP_URL_PATH));
            $ext = strtolower(pathinfo($ext, PATHINFO_EXTENSION));
            return in_array($ext, ['mp4', 'webm', 'mov'], true);
        }

        return false;
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
