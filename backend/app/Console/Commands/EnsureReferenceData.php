<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Seeds the reference data every deployment needs (roles/permissions, the
 * first admin and the medical knowledge base) exactly once per database.
 *
 * The seeders are slow on a cold remote database (hundreds of individual
 * round-trips), so the Render entrypoint runs this command in the background
 * instead of blocking the port bind / deploy health check. A marker in the
 * persistent `database` cache store makes later boots skip the work, while an
 * interrupted run (container restarted mid-seed) is retried because the marker
 * is only written after the seeders finish.
 */
class EnsureReferenceData extends Command
{
    protected $signature = 'app:ensure-reference-data';

    protected $description = 'Seed roles, the first admin and the knowledge base once per database.';

    public function handle(): int
    {
        $cache = Cache::store('database');
        $marker = 'reference-data-seeded';

        if ($cache->get($marker) !== null) {
            $this->info('Reference data already seeded.');

            return self::SUCCESS;
        }

        $this->info('Seeding reference data...');

        $startedAt = microtime(true);

        try {
            $exitCode = $this->call('db:seed', [
                '--class' => ProductionSeeder::class,
                '--force' => true,
            ]);
        } catch (Throwable $exception) {
            $this->error(sprintf(
                'Reference data seeding failed after %.1fs: %s',
                microtime(true) - $startedAt,
                $exception->getMessage()
            ));
            $this->error('It will be retried on the next boot.');

            report($exception);

            return self::FAILURE;
        }

        if ($exitCode !== self::SUCCESS) {
            $this->error(sprintf(
                'Reference data seeding failed after %.1fs (exit code %d); it will be retried on the next boot.',
                microtime(true) - $startedAt,
                $exitCode
            ));

            return $exitCode;
        }

        $cache->forever($marker, now()->toIso8601String());

        $this->info(sprintf('Reference data seeded in %.1fs.', microtime(true) - $startedAt));

        return self::SUCCESS;
    }
}
