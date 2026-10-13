<?php

namespace App\Services\Telegram;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TelegramConnection;
use App\Models\TelegramDeliveryLog;
use App\Services\Communication\ConversationService;

/**
 * Resolves and records inbound Telegram text into a conversation.
 *
 * The target conversation is resolved in two ways, in order of precedence:
 *   1. a native Telegram reply to a previously delivered bot message
 *      (Option 1 notification-reply path), mapped via
 *      `telegram_delivery_logs`; then
 *   2. the conversation the chat explicitly selected with `/open`.
 *
 * A message is only accepted when the Telegram chat is linked to a verified
 * RoyalCare user, the resolved conversation has that user as an active
 * participant, the user holds the `chat.send` permission and the body is
 * non-empty and within the configured length. Every inbound Telegram message
 * is de-duplicated on (chat_id, message_id).
 */
class TelegramReplyService
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_NOT_LINKED = 'not_linked';

    public const STATUS_NOT_RECOGNIZED = 'not_recognized';

    public const STATUS_UNAUTHORIZED = 'unauthorized';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_TOO_LONG = 'too_long';

    public function __construct(private readonly ConversationService $conversations) {}

    /**
     * @return array{status: string, conversation: Conversation|null}
     */
    public function handle(
        int|string $chatId,
        ?int $inboundMessageId,
        ?int $replyToMessageId,
        string $text,
    ): array {
        $connection = TelegramConnection::query()
            ->where('telegram_chat_id', $chatId)
            ->whereNotNull('verified_at')
            ->first();

        $user = $connection?->user;

        if ($user === null) {
            return $this->result(self::STATUS_NOT_LINKED);
        }

        $conversation = $this->resolveConversation($connection, $chatId, $replyToMessageId);

        if (! $conversation instanceof Conversation) {
            return $this->result(self::STATUS_NOT_RECOGNIZED);
        }

        if (! $user->can('chat.send') || ! $conversation->hasActiveParticipant($user)) {
            return $this->result(self::STATUS_UNAUTHORIZED);
        }

        $body = trim($text);

        if ($body === '') {
            return $this->result(self::STATUS_EMPTY);
        }

        $limit = (int) config('communication.chat.max_message_length', 4000);

        if (mb_strlen($body) > $limit) {
            return $this->result(self::STATUS_TOO_LONG);
        }

        if ($inboundMessageId !== null && $this->alreadyStored($chatId, $inboundMessageId)) {
            return $this->result(self::STATUS_DUPLICATE, $conversation);
        }

        $message = $this->conversations->sendTelegramReply(
            $conversation,
            $user,
            $body,
            $chatId,
            $inboundMessageId,
        );

        return $this->result(self::STATUS_RECORDED, $message->conversation);
    }

    /**
     * Prefer the conversation the message replies to; otherwise fall back to
     * the conversation explicitly selected via `/open`.
     */
    private function resolveConversation(
        TelegramConnection $connection,
        int|string $chatId,
        ?int $replyToMessageId,
    ): ?Conversation {
        if ($replyToMessageId !== null) {
            $log = TelegramDeliveryLog::query()
                ->where('telegram_chat_id', $chatId)
                ->where('telegram_message_id', $replyToMessageId)
                ->whereNotNull('conversation_id')
                ->first();

            return $log?->conversation;
        }

        $active = $connection->activeConversation;

        return $active instanceof Conversation ? $active : null;
    }

    private function alreadyStored(int|string $chatId, int $inboundMessageId): bool
    {
        return ConversationMessage::query()
            ->where('telegram_chat_id', $chatId)
            ->where('telegram_message_id', $inboundMessageId)
            ->exists();
    }

    /**
     * @return array{status: string, conversation: Conversation|null}
     */
    private function result(string $status, ?Conversation $conversation = null): array
    {
        return ['status' => $status, 'conversation' => $conversation];
    }
}
