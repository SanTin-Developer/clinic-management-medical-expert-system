<?php

namespace App\Services\Telegram;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramLinkService
{
    public const STATUS_LINKED = 'linked';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_USED = 'used';

    public const STATUS_CONFLICT = 'conflict';

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

    /**
     * Atomically validate and consume a linking token, binding the Telegram
     * chat to the token's owner. The binding is derived exclusively from the
     * server-stored token — never from client-supplied input.
     *
     * @return array{status: string, connection: ?TelegramConnection}
     */
    public function consumeToken(string $plain, int $chatId, ?string $username): array
    {
        $hash = hash('sha256', $plain);

        return DB::transaction(function () use ($hash, $chatId, $username): array {
            $token = TelegramLinkingToken::query()
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $token) {
                return ['status' => self::STATUS_INVALID, 'connection' => null];
            }

            if ($token->isUsed()) {
                // Re-sending the same code from the chat that already linked
                // with it is idempotent: the first delivery connected the
                // account, so confirm success instead of telling the user
                // their freshly generated code was "already used".
                $existing = TelegramConnection::query()
                    ->where('telegram_chat_id', $chatId)
                    ->where('user_id', $token->user_id)
                    ->lockForUpdate()
                    ->first();

                return $existing
                    ? ['status' => self::STATUS_LINKED, 'connection' => $existing]
                    : ['status' => self::STATUS_USED, 'connection' => null];
            }

            if ($token->isExpired()) {
                return ['status' => self::STATUS_EXPIRED, 'connection' => null];
            }

            $conflict = TelegramConnection::query()
                ->where('telegram_chat_id', $chatId)
                ->where('user_id', '!=', $token->user_id)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return ['status' => self::STATUS_CONFLICT, 'connection' => null];
            }

            $connection = TelegramConnection::updateOrCreate(
                ['user_id' => $token->user_id],
                [
                    'telegram_chat_id' => $chatId,
                    'telegram_username' => $username,
                    'verified_at' => now(),
                ],
            );

            $token->update(['used_at' => now()]);

            return ['status' => self::STATUS_LINKED, 'connection' => $connection];
        });
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
