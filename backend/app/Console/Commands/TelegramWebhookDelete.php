<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Throwable;

class TelegramWebhookDelete extends Command
{
    protected $signature = 'telegram:webhook:delete {--drop-pending : Also discard updates already queued on Telegram}';

    protected $description = 'Remove the RoyalCare Telegram webhook (switch to polling delivery mode).';

    public function handle(TelegramClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Telegram is not configured (TELEGRAM_ENABLED / TELEGRAM_BOT_TOKEN).');

            return self::FAILURE;
        }

        try {
            $client->deleteWebhook((bool) $this->option('drop-pending'));
        } catch (Throwable $exception) {
            $this->error('Failed to remove webhook: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook removed. Polling can now receive updates via telegram:poll.');

        return self::SUCCESS;
    }
}
