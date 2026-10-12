<?php

namespace App\Services\Telegram;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramLinkService
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Issue a fresh single-use linking token for the given user.
     *
     * @return array{token: string, deep_link: ?string, expires_at: string}
     */
    public function generateToken(User $user): array
    {
        TelegramLinkingToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plain = Str::random(48);

        $token = TelegramLinkingToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes((int) ($this->config['link_token_ttl_minutes'] ?? 15)),
        ]);

        $botUsername = $this->config['bot_username'] ?? null;

        return [
            'token' => $plain,
            'deep_link' => filled($botUsername)
                ? 'https://t.me/'.ltrim((string) $botUsername, '@').'?start='.$plain
                : null,
            'expires_at' => $token->expires_at->toIso8601String(),
        ];
    }

    public function consumeToken(string $plain, int $chatId, ?string $username): ?TelegramConnection
    {
        $token = TelegramLinkingToken::query()
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        if (! $token || ! $token->isUsable()) {
            return null;
        }

        TelegramConnection::query()
            ->where('telegram_chat_id', $chatId)
            ->where('user_id', '!=', $token->user_id)
            ->delete();

        $connection = TelegramConnection::updateOrCreate(
            ['user_id' => $token->user_id],
            [
                'telegram_chat_id' => $chatId,
                'telegram_username' => $username,
                'verified_at' => now(),
            ],
        );

        $token->update(['used_at' => now()]);

        return $connection;
    }

    public function unlinkByChatId(int $chatId): bool
    {
        $connection = TelegramConnection::query()
            ->where('telegram_chat_id', $chatId)
            ->first();

        if (! $connection) {
            return false;
        }

        $connection->delete();

        return true;
    }

    public function unlink(User $user): void
    {
        $user->telegramConnection()->delete();
    }
}
