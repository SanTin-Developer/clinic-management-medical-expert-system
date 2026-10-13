<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polling delivery mode for Telegram.
 *
 * Webhook and polling are mutually exclusive: Telegram refuses getUpdates while
 * a webhook is registered, so this worker removes any webhook before it starts
 * listening. When the webhook mode is enabled in configuration (or polling is
 * disabled), the worker exits cleanly so the two modes can never run together.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll
        {--once : Process the currently pending updates once, then exit}
        {--timeout= : Long-poll timeout in seconds}
        {--sleep= : Seconds to idle between empty polls}';

    protected $description = 'Poll Telegram for updates and handle account-linking commands (alternative to the webhook).';

    public function handle(TelegramClient $client, TelegramUpdateHandler $handler): int
    {
        if ((bool) config('communication.telegram.webhook_enabled')) {
            $this->info('Webhook mode is enabled; telegram:poll is disabled by design.');

            return self::SUCCESS;
        }

        if (! (bool) config('communication.telegram.polling_enabled')) {
            $this->info('Polling is disabled (TELEGRAM_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $client->isConfigured()) {
            $this->error('Telegram is not configured (TELEGRAM_ENABLED / TELEGRAM_BOT_TOKEN).');

            return self::FAILURE;
        }

        try {
            $info = $client->getWebhookInfo();

            if (filled($info['url'] ?? null)) {
                $this->warn('A webhook is registered; removing it so polling can receive updates.');
                $client->deleteWebhook();
            }
        } catch (Throwable $exception) {
            $this->error('Unable to verify webhook state: '.$exception->getMessage());

            return self::FAILURE;
        }

        $timeout = (int) ($this->option('timeout') ?: config('communication.telegram.poll_timeout', 30));
        $sleep = (int) ($this->option('sleep') ?: config('communication.telegram.poll_sleep', 1));
        $once = (bool) $this->option('once');

        $offset = 0;

        $this->info('Polling Telegram for updates…');

        do {
            try {
                $updates = $client->getUpdates($offset, $timeout);
            } catch (Throwable $exception) {
                $this->error('getUpdates failed: '.$exception->getMessage());
                sleep($sleep);

                if ($once) {
                    return self::FAILURE;
                }

                continue;
            }

            foreach ($updates as $update) {
                $offset = max($offset, ((int) ($update['update_id'] ?? 0)) + 1);
                $handler->handle($update);
            }

            if ($once) {
                if ($updates !== []) {
                    // Confirm the advanced offset so Telegram drops the
                    // updates we just handled instead of redelivering them.
                    try {
                        $client->getUpdates($offset, 0);
                    } catch (Throwable $exception) {
                        $this->error('Failed to confirm update offset: '.$exception->getMessage());
                    }
                }

                break;
            }

            if ($updates === []) {
                sleep($sleep);
            }
        } while (true);

        return self::SUCCESS;
    }
}
