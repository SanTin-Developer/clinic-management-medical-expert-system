<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Translates raw Telegram updates into RoyalCare account linking actions.
 *
 * Shared by the public webhook controller and the `telegram:poll` worker so
 * both delivery modes behave identically. Updates are de-duplicated by
 * `update_id` because Telegram retries any update it believes was not
 * acknowledged.
 */
class TelegramUpdateHandler
{
    private const DEDUPE_TTL_HOURS = 6;

    public function __construct(
        private readonly TelegramLinkService $links,
        private readonly TelegramClient $client,
        private readonly TelegramReplyService $replies,
        private readonly TelegramConversationService $conversations,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $updateId = $update['update_id'] ?? null;

        if ($updateId !== null
            && ! Cache::add('telegram:update:'.$updateId, true, now()->addHours(self::DEDUPE_TTL_HOURS))) {
            return;
        }

        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if ($chatId === null || ! is_string($text) || $text === '') {
            return;
        }

        $username = $message['from']['username'] ?? null;

        // Commands always take precedence, even when sent as a reply.
        if (str_starts_with(ltrim($text), '/')) {
            $this->handleCommand(
                (int) $chatId,
                $text,
                is_string($username) && $username !== '' ? $username : null,
            );

            return;
        }

        $this->handleText(
            (int) $chatId,
            $this->numericId($message['message_id'] ?? null),
            $this->numericId($message['reply_to_message']['message_id'] ?? null),
            $text,
        );
    }

    private function handleText(int $chatId, ?int $inboundMessageId, ?int $replyToMessageId, string $text): void
    {
        $result = $this->replies->handle($chatId, $inboundMessageId, $replyToMessageId, $text);

        $message = match ($result['status']) {
            TelegramReplyService::STATUS_RECORDED,
            TelegramReplyService::STATUS_DUPLICATE => $replyToMessageId !== null
                ? TelegramMessages::replyRecorded($result['conversation']?->title)
                : TelegramMessages::messageSent($result['conversation']?->title),
            TelegramReplyService::STATUS_NOT_LINKED => TelegramMessages::replyNotLinked(),
            TelegramReplyService::STATUS_UNAUTHORIZED => TelegramMessages::replyUnauthorized(),
            TelegramReplyService::STATUS_EMPTY => TelegramMessages::replyEmpty(),
            TelegramReplyService::STATUS_TOO_LONG => TelegramMessages::replyTooLong(
                (int) config('communication.chat.max_message_length', 4000),
            ),
            default => $replyToMessageId !== null
                ? TelegramMessages::replyNotRecognized()
                : TelegramMessages::noActiveConversation(),
        };

        $this->safeReply($chatId, $message);
    }

    private function numericId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function handleCommand(int $chatId, string $text, ?string $username): void
    {
        [$command, $argument] = $this->parseCommand($text);

        if ($command === '/start') {
            if ($argument === null) {
                $this->safeReply($chatId, TelegramMessages::welcome());

                return;
            }

            $result = $this->links->consumeToken($argument, $chatId, $username);

            $this->safeReply($chatId, $this->replyForStatus($result['status'], $username));

            return;
        }

        if ($command === '/stop') {
            $removed = $this->links->unlinkByChatId($chatId);

            $this->safeReply($chatId, $removed
                ? TelegramMessages::unlinked()
                : TelegramMessages::alreadyUnlinked());

            return;
        }

        if ($command === '/help') {
            $this->safeReply($chatId, TelegramMessages::help());

            return;
        }

        if ($command === '/chats') {
            $this->safeReply($chatId, $this->chatsMessage($chatId));

            return;
        }

        if ($command === '/open') {
            $this->safeReply($chatId, $this->openMessage($chatId, $argument));

            return;
        }

        if ($command === '/close') {
            $this->safeReply($chatId, $this->closeMessage($chatId));

            return;
        }

        // Reply helpfully to unrecognised commands. Plain chat text is handled
        // as a potential reply before this method is reached.
        if (str_starts_with($command, '/')) {
            $this->safeReply($chatId, TelegramMessages::help());
        }
    }

    private function chatsMessage(int $chatId): string
    {
        $result = $this->conversations->listForChat($chatId);

        if ($result['status'] === TelegramConversationService::STATUS_NOT_LINKED) {
            return TelegramMessages::replyNotLinked();
        }

        return TelegramMessages::conversationList($result['conversations'], $result['active_id']);
    }

    private function openMessage(int $chatId, ?string $argument): string
    {
        $result = $this->conversations->open($chatId, $argument);

        return match ($result['status']) {
            TelegramConversationService::STATUS_OPENED => TelegramMessages::conversationOpened($result['title'] ?? ''),
            TelegramConversationService::STATUS_INVALID => TelegramMessages::openUsage(),
            TelegramConversationService::STATUS_FORBIDDEN => TelegramMessages::conversationForbidden(),
            TelegramConversationService::STATUS_NOT_LINKED => TelegramMessages::replyNotLinked(),
            default => TelegramMessages::conversationNotFound(),
        };
    }

    private function closeMessage(int $chatId): string
    {
        $result = $this->conversations->close($chatId);

        if ($result['status'] === TelegramConversationService::STATUS_NOT_LINKED) {
            return TelegramMessages::replyNotLinked();
        }

        return TelegramMessages::conversationClosed();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/', trim($text), 2) ?: [];

        $command = strtolower($parts[0] ?? '');

        if (str_contains($command, '@')) {
            $command = substr($command, 0, (int) strpos($command, '@'));
        }

        $argument = isset($parts[1]) ? trim($parts[1]) : null;

        return [$command, $argument === '' ? null : $argument];
    }

    private function replyForStatus(string $status, ?string $username): string
    {
        return match ($status) {
            TelegramLinkService::STATUS_LINKED => TelegramMessages::linkingSuccess($username),
            TelegramLinkService::STATUS_EXPIRED => TelegramMessages::expiredCode(),
            TelegramLinkService::STATUS_USED => TelegramMessages::usedCode(),
            TelegramLinkService::STATUS_CONFLICT => TelegramMessages::conflictingAccount(),
            default => TelegramMessages::invalidCode(),
        };
    }

    private function safeReply(int $chatId, string $text): void
    {
        try {
            $this->client->sendMessage($chatId, $text);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
