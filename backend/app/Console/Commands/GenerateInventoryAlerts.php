<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\Alerts\SystemAlertGenerator;
use Illuminate\Console\Command;

class GenerateInventoryAlerts extends Command
{
    protected $signature = 'alerts:generate-inventory';

    protected $description = 'Generate and resolve inventory system alerts (compatibility wrapper)';

    public function handle(SystemAlertGenerator $generator): int
    {
        $result = $generator->generate([SystemAlert::CATEGORY_INVENTORY])[SystemAlert::CATEGORY_INVENTORY] ?? null;

        if ($result === null) {
            return self::FAILURE;
        }

        if (isset($result['error'])) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("Processed {$result['seen']} inventory condition(s); resolved {$result['resolved']}.");

        return self::SUCCESS;
    }
}
