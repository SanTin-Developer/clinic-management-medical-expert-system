<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\Alerts\SystemAlertGenerator;
use Illuminate\Console\Command;

class GenerateSystemAlerts extends Command
{
    protected $signature = 'alerts:generate
        {--only= : Comma-separated categories to scan (inventory, inpatient_billing, appointments, staff, attendance, clinical, system)}';

    protected $description = 'Generate and resolve system alerts from operational conditions';

    public function handle(SystemAlertGenerator $generator): int
    {
        $only = null;

        if (is_string($this->option('only')) && trim($this->option('only')) !== '') {
            $only = array_values(array_filter(array_map('trim', explode(',', $this->option('only')))));

            $invalid = array_diff($only, SystemAlert::CATEGORIES);

            if ($invalid !== []) {
                $this->error('Unknown alert category: '.implode(', ', $invalid));

                return self::FAILURE;
            }
        }

        $results = $generator->generate($only);
        $failed = false;

        foreach ($results as $category => $result) {
            if (isset($result['error'])) {
                $failed = true;
                $this->error("{$category}: {$result['error']}");
                continue;
            }

            $this->line(sprintf(
                '%s: %d active condition(s), %d resolved',
                $category,
                $result['seen'],
                $result['resolved'],
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
