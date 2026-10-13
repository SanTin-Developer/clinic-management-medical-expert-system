<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && filled($this->config['bot_token'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $options));
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, ?string $secret = null): array
    {
        return $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'my_chat_member'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => $dropPending]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        $response = $this->call('getWebhookInfo');

        return $response['result'] ?? [];
    }

    /**
     * Long-poll Telegram for new updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0, int $timeout = 0): array
    {
        $response = $this->call('getUpdates', array_filter([
            'offset' => $offset > 0 ? $offset : null,
            'timeout' => $timeout,
            'allowed_updates' => ['message'],
        ], fn ($value) => $value !== null), $timeout + (int) ($this->config['timeout'] ?? 10) + 5);

        return $response['result'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload = [], ?int $timeout = null): array
    {
        $token = $this->config['bot_token'] ?? null;

        if (! filled($token)) {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $response = Http::timeout($timeout ?? (int) ($this->config['timeout'] ?? 10))
            ->baseUrl(rtrim((string) ($this->config['api_base_url'] ?? 'https://api.telegram.org'), '/'))
            ->asJson()
            ->post('/bot'.$token.'/'.$method, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Telegram API call [{$method}] failed with status {$response->status()}: ".$response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
