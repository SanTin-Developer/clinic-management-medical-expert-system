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

// Publish announcements whose scheduled publication time has arrived.
// Idempotent, so a short interval is safe.
Schedule::command('announcements:publish-scheduled')->everyMinute()->withoutOverlapping();

// Notify assigned nurses when nursing tasks become overdue (grace period configurable).
Schedule::command('nursing:notify-overdue --grace=5')->everyMinute()->withoutOverlapping()->runInBackground();

// Generate and resolve system alerts across inventory, inpatient billing,
// appointments, staff, attendance, clinical, and infrastructure health.
// Idempotent by source key, so a short interval is safe.
Schedule::command('alerts:generate')->everyFiveMinutes()->withoutOverlapping();
