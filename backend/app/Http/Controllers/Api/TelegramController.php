<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramGroupConfig;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(
        private readonly TelegramLinkService $links,
        private readonly TelegramClient $client,
        private readonly TelegramUpdateHandler $updates,
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

        $webhookUrl = $this->resolveWebhookUrl();

        if (! str_starts_with($webhookUrl, 'https://')) {
            return response()->json([
                'message' => 'Telegram requires a public HTTPS webhook URL. Set TELEGRAM_WEBHOOK_URL or use polling.',
                'data' => ['url' => $webhookUrl],
            ], 422);
        }

        $this->client->setWebhook($webhookUrl, config('communication.telegram.webhook_secret'));

        return response()->json([
            'message' => 'Telegram webhook registered.',
            'data' => ['url' => $webhookUrl],
        ]);
    }

    public function unregisterWebhook(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'telegram.group.manage');

        abort_unless($this->client->isConfigured(), 503, 'Telegram bot token is not configured.');

        $this->client->deleteWebhook();

        return response()->json([
            'message' => 'Telegram webhook removed.',
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

        $this->updates->handle($request->all());

        return response()->json(['ok' => true]);
    }

    private function resolveWebhookUrl(): string
    {
        $configured = (string) (config('communication.telegram.webhook_url') ?? '');

        return $configured !== '' ? $configured : url('/api/v1/telegram/webhook');
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
