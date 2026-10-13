<?php

namespace App\Services\Telegram;

use App\Models\Conversation;
use App\Models\TelegramConnection;
use App\Models\User;

/**
 * Manages a linked Telegram chat's explicit conversation selection.
 *
 * The bot never guesses a target from "most recent activity": staff must list
 * their conversations with `/chats` and choose one with `/open <id>`. Selection
 * is stored as integration metadata on the connection and re-validated against
 * current membership on every use.
 */
class TelegramConversationService
{
    public const STATUS_OK = 'ok';

    public const STATUS_NOT_LINKED = 'not_linked';

    public const STATUS_OPENED = 'opened';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_FORBIDDEN = 'forbidden';

    public const STATUS_CLOSED = 'closed';

    private const MAX_LISTED = 20;

    /**
     * @return array{status: string, conversations: array<int, array{id: int, title: string, type: string}>, active_id: int|null}
     */
    public function listForChat(int|string $chatId): array
    {
        $connection = $this->connection($chatId);
        $user = $connection?->user;

        if ($connection === null || $user === null) {
            return ['status' => self::STATUS_NOT_LINKED, 'conversations' => [], 'active_id' => null];
        }

        $conversations = $this->conversationsFor($user)
            ->with('activeParticipants.user')
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->displayTitleFor($user),
                'type' => $conversation->type,
            ])
            ->all();

        return [
            'status' => self::STATUS_OK,
            'conversations' => $conversations,
            'active_id' => $this->activeId($connection, $user),
        ];
    }

    /**
     * @return array{status: string, conversation?: Conversation, title?: string}
     */
    public function open(int|string $chatId, ?string $argument): array
    {
        $connection = $this->connection($chatId);
        $user = $connection?->user;

        if ($connection === null || $user === null) {
            return ['status' => self::STATUS_NOT_LINKED];
        }

        $argument = $argument === null ? '' : trim($argument);

        if ($argument === '' || ! ctype_digit($argument)) {
            return ['status' => self::STATUS_INVALID];
        }

        $conversation = $this->conversationsFor($user)
            ->whereKey((int) $argument)
            ->with('activeParticipants.user')
            ->first();

        if (! $conversation instanceof Conversation) {
            return ['status' => self::STATUS_NOT_FOUND];
        }

        if (! $user->can('chat.send')) {
            return ['status' => self::STATUS_FORBIDDEN];
        }

        $connection->forceFill(['active_conversation_id' => $conversation->id])->save();

        return [
            'status' => self::STATUS_OPENED,
            'conversation' => $conversation,
            'title' => $conversation->displayTitleFor($user),
        ];
    }

    /**
     * @return array{status: string}
     */
    public function close(int|string $chatId): array
    {
        $connection = $this->connection($chatId);

        if ($connection === null) {
            return ['status' => self::STATUS_NOT_LINKED];
        }

        $connection->forceFill(['active_conversation_id' => null])->save();

        return ['status' => self::STATUS_CLOSED];
    }

    private function connection(int|string $chatId): ?TelegramConnection
    {
        return TelegramConnection::query()
            ->where('telegram_chat_id', $chatId)
            ->whereNotNull('verified_at')
            ->first();
    }

    /**
     * Active conversations the user still participates in, most recently
     * updated first.
     */
    private function conversationsFor(User $user)
    {
        return $user->conversations()
            ->wherePivotNull('left_at')
            ->orderByDesc('conversations.updated_at')
            ->limit(self::MAX_LISTED);
    }

    private function activeId(TelegramConnection $connection, User $user): ?int
    {
        $id = $connection->active_conversation_id;

        if ($id === null) {
            return null;
        }

        $stillMember = $user->conversations()
            ->wherePivotNull('left_at')
            ->whereKey($id)
            ->exists();

        return $stillMember ? $id : null;
    }
}
