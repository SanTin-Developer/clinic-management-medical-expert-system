<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Throwable;

class TelegramWebhookSet extends Command
{
    protected $signature = 'telegram:webhook:set {--url= : Public HTTPS webhook URL (defaults to TELEGRAM_WEBHOOK_URL)}';

    protected $description = 'Register the RoyalCare Telegram webhook (webhook delivery mode).';

    public function handle(TelegramClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Telegram is not configured (TELEGRAM_ENABLED / TELEGRAM_BOT_TOKEN).');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: config('communication.telegram.webhook_url') ?: '');

        if ($url === '') {
            $this->error('No webhook URL provided. Pass --url or set TELEGRAM_WEBHOOK_URL.');

            return self::FAILURE;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->error('Telegram requires a public HTTPS webhook URL.');

            return self::FAILURE;
        }

        $secret = (string) config('communication.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET must be set so incoming updates can be authenticated.');

            return self::FAILURE;
        }

        try {
            $client->setWebhook($url, $secret);
        } catch (Throwable $exception) {
            $this->error('Failed to register webhook: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook registered. The telegram:poll worker will disable itself while this webhook is active.');

        return self::SUCCESS;
    }
}
