<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Close QR attendance sessions whose window elapsed and finalize attendance
// (absence records, missing clock-out flags). Idempotent, so a short interval
// simply keeps the live dashboard and reports timely.
Schedule::command('attendance:finalize')->everyMinute()->withoutOverlapping()->runInBackground();
