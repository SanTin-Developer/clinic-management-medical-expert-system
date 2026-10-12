<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramGroupConfig;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class TelegramController extends Controller
{
    public function __construct(
        private readonly TelegramLinkService $links,
        private readonly TelegramClient $client,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.connect');

        $connection = $request->user()->telegramConnection;

        return response()->json([
            'data' => [
                'enabled' => (bool) config('communication.telegram.enabled'),
                'bot_username' => config('communication.telegram.bot_username'),
                'linked' => $connection?->isVerified() ?? false,
                'telegram_username' => $connection?->telegram_username,
                'linked_at' => $connection?->verified_at?->toIso8601String(),
            ],
        ]);
    }

    public function link(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.connect');

        abort_unless((bool) config('communication.telegram.enabled'), 503, 'Telegram integration is disabled.');

        $result = $this->links->generateToken($request->user());

        return response()->json([
            'message' => 'Open Telegram and send the start command to link your account.',
            'data' => $result,
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.connect');

        $this->links->unlink($request->user());

        return response()->json([
            'message' => 'Telegram account unlinked.',
        ]);
    }

    public function linkWebhook(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.group.manage');

        abort_unless($this->client->isConfigured(), 503, 'Telegram bot token is not configured.');

        $webhookUrl = url('/api/v1/telegram/webhook');

        $this->client->setWebhook($webhookUrl, config('communication.telegram.webhook_secret'));

        return response()->json([
            'message' => 'Telegram webhook registered.',
            'data' => ['url' => $webhookUrl],
        ]);
    }

    public function groupConfig(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.group.manage');

        return response()->json([
            'data' => $this->serializeGroupConfig(TelegramGroupConfig::current()),
        ]);
    }

    public function updateGroupConfig(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.group.manage');

        $validated = $request->validate([
            'chat_id' => ['nullable', 'integer'],
            'is_enabled' => ['sometimes', 'boolean'],
            'event_categories' => ['sometimes', 'array'],
            'event_categories.*' => ['in:'.implode(',', TelegramGroupConfig::CATEGORIES)],
        ]);

        $config = TelegramGroupConfig::current();
        $config->fill($validated);
        $config->updated_by = $request->user()->id;
        $config->save();

        return response()->json([
            'message' => 'Telegram group configuration updated.',
            'data' => $this->serializeGroupConfig($config->fresh()),
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('communication.telegram.webhook_secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless(
            (bool) config('communication.telegram.webhook_enabled')
                && filled($secret)
                && is_string($provided)
                && hash_equals($secret, $provided),
            403,
        );

        $message = $request->input('message');

        if (is_array($message) && isset($message['text'], $message['chat']['id'])) {
            $this->handleCommand(
                (int) $message['chat']['id'],
                (string) $message['text'],
                $message['from']['username'] ?? null,
            );
        }

        return response()->json(['ok' => true]);
    }

    private function handleCommand(int $chatId, string $text, ?string $username): void
    {
        $text = trim($text);

        if (Str::startsWith($text, '/start')) {
            $parts = preg_split('/\s+/', $text) ?: [];
            $token = $parts[1] ?? null;

            if (filled($token)) {
                $connection = $this->links->consumeToken($token, $chatId, $username);

                $this->safeReply($chatId, $connection
                    ? 'Your RoyalCare account is now linked. You will receive notifications here.'
                    : 'This linking code is invalid or has expired. Generate a new one from RoyalCare.');
            } else {
                $this->safeReply($chatId, 'Welcome to RoyalCare. Open Settings → Telegram in the app to link your account.');
            }

            return;
        }

        if (Str::startsWith($text, '/stop')) {
            $removed = $this->links->unlinkByChatId($chatId);

            $this->safeReply($chatId, $removed
                ? 'You have been unlinked and will no longer receive notifications here.'
                : 'This chat is not linked to any RoyalCare account.');
        }
    }

    private function safeReply(int $chatId, string $text): void
    {
        try {
            $this->client->sendMessage($chatId, $text);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        abort_unless($request->user()?->can($ability), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroupConfig(TelegramGroupConfig $config): array
    {
        return [
            'chat_id' => $config->chat_id,
            'is_enabled' => (bool) $config->is_enabled,
            'event_categories' => $config->event_categories ?? [],
            'updated_at' => $config->updated_at?->toIso8601String(),
        ];
    }
}
