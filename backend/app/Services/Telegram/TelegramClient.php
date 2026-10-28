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
     * Send a photo by public URL or by uploading local bytes.
     *
     * @param  resource|string  $photo
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendPhoto(int|string $chatId, mixed $photo, string $caption = '', ?string $filename = null, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        if (is_string($photo) && preg_match('#^https?://#i', $photo) === 1) {
            return $this->call('sendPhoto', array_merge($payload, ['photo' => $photo]));
        }

        return $this->call('sendPhoto', $payload, null, [
            'name' => 'photo',
            'contents' => $photo,
            'filename' => $filename,
        ]);
    }

    /**
     * Send a document by public URL or by uploading local bytes.
     *
     * @param  resource|string  $document
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendDocument(int|string $chatId, mixed $document, string $caption = '', ?string $filename = null, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        if (is_string($document) && preg_match('#^https?://#i', $document) === 1) {
            return $this->call('sendDocument', array_merge($payload, ['document' => $document]));
        }

        return $this->call('sendDocument', $payload, null, [
            'name' => 'document',
            'contents' => $document,
            'filename' => $filename,
        ]);
    }

    /**
     * Send a video by public URL or by uploading local bytes.
     *
     * @param  resource|string  $video
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendVideo(int|string $chatId, mixed $video, string $caption = '', ?string $filename = null, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        if (is_string($video) && preg_match('#^https?://#i', $video) === 1) {
            return $this->call('sendVideo', array_merge($payload, ['video' => $video]));
        }

        return $this->call('sendVideo', $payload, null, [
            'name' => 'video',
            'contents' => $video,
            'filename' => $filename,
        ]);
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
     * @return array<string, mixed>
     */
    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
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
     * @param  array{name: string, contents: mixed, filename: string|null}|null  $file
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload = [], ?int $timeout = null, ?array $file = null): array
    {
        $token = $this->config['bot_token'] ?? null;

        if (! filled($token)) {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $request = Http::timeout($timeout ?? (int) ($this->config['timeout'] ?? 10))
            ->baseUrl(rtrim((string) ($this->config['api_base_url'] ?? 'https://api.telegram.org'), '/'));

        if ($file !== null) {
            $request = $request->attach($file['name'], $file['contents'], $file['filename']);
        } else {
            $request = $request->asJson();
        }

        $response = $request->post('/bot'.$token.'/'.$method, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Telegram API call [{$method}] failed with status {$response->status()}: ".$response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
