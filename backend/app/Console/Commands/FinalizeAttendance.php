<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceFinalizationService;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Closes QR sessions whose clock-in/clock-out window has elapsed and finalizes
 * attendance for shifts whose clock-in window has passed (absence records,
 * missing clock-out flags). Safe to run frequently: all operations are
 * idempotent.
 */
class FinalizeAttendance extends Command
{
    protected $signature = 'attendance:finalize
        {--lookback=7 : Number of previous days to backfill}';

    protected $description = 'Close expired QR sessions and finalize attendance (absences, missing clock-outs).';

    public function handle(
        AttendanceSessionService $sessions,
        AttendanceFinalizationService $finalization,
    ): int {
        $closed = $sessions->closeExpired();
        $touched = $finalization->finalizeDue(
            CarbonImmutable::now(),
            (int) $this->option('lookback'),
        );

        $this->info("Closed {$closed} expired session(s); finalized {$touched} attendance record run(s).");

        return self::SUCCESS;
    }
}
