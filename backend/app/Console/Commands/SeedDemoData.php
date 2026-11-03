<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Seeds the optional demo dataset (demo logins, the 40-person staff directory
 * and the production-like page data) once per database.
 *
 * The Render `initialDeployHook` only runs on the FIRST successful deploy, so
 * enabling SEED_DEMO_DATA after a service is already live would never take
 * effect. The entrypoint therefore calls this command on every boot instead,
 * mirroring EnsureReferenceData: a marker in the persistent `database` cache
 * store makes later boots skip the work, and an interrupted run is retried
 * because the marker is only written after the seeders finish.
 *
 * The seeders are idempotent, so a retry is safe.
 */
class SeedDemoData extends Command
{
    protected $signature = 'app:seed-demo-data';

    protected $description = 'Seed the demo dataset (staff directory and page data) once per database.';

    public function handle(): int
    {
        if (! $this->demoDataEnabled()) {
            $this->info('SEED_DEMO_DATA is disabled; skipping demo data.');

            return self::SUCCESS;
        }

        $cache = Cache::store('database');
        $marker = 'demo-data-seeded-v1';

        if ($cache->get($marker) !== null) {
            $this->info('Demo data already seeded.');

            return self::SUCCESS;
        }

        $this->info('Seeding demo data...');

        $startedAt = microtime(true);

        try {
            $exitCode = $this->call('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => true,
            ]);
        } catch (Throwable $exception) {
            $this->error(sprintf(
                'Demo data seeding failed after %.1fs: %s',
                microtime(true) - $startedAt,
                $exception->getMessage()
            ));
            $this->error('It will be retried on the next boot.');

            report($exception);

            return self::FAILURE;
        }

        if ($exitCode !== self::SUCCESS) {
            $this->error(sprintf(
                'Demo data seeding failed after %.1fs (exit code %d); it will be retried on the next boot.',
                microtime(true) - $startedAt,
                $exitCode
            ));

            return $exitCode;
        }

        $cache->forever($marker, now()->toIso8601String());

        $this->info(sprintf('Demo data seeded in %.1fs.', microtime(true) - $startedAt));

        return self::SUCCESS;
    }

    private function demoDataEnabled(): bool
    {
        return filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL);
    }
}
