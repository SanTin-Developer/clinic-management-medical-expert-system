<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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

        $exitCode = $this->call('db:seed', [
            '--class' => ProductionSeeder::class,
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error('Reference data seeding failed; it will be retried on the next boot.');

            return $exitCode;
        }

        $cache->forever($marker, now()->toIso8601String());

        $this->info('Reference data seeded.');

        return self::SUCCESS;
    }
}
