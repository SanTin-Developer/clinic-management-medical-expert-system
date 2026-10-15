<?php

namespace App\Services\Attendance;

use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;

/**
 * Translates clinic-local shift template times into absolute UTC instants.
 *
 * The application timezone is UTC, so all comparisons (server clock-in time vs
 * scheduled start, session opening vs cutoff) happen on UTC instants. Authoring
 * the shift in the clinic timezone and projecting it to UTC makes overnight
 * shifts "belong" to their scheduled starting date while still ending on the
 * following calendar day.
 */
class ShiftSchedule
{
    public function timezone(): string
    {
        return (string) config('attendance.timezone');
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, cutoff: CarbonImmutable, out_opens: CarbonImmutable, out_closes: CarbonImmutable}
     */
    public function windowFor(ShiftTemplate $template, string $date): array
    {
        $tz = $this->timezone();

        $start = $this->at($date, $template->start_time, $tz);

        $end = $this->at($date, $template->end_time, $tz);
        if ($template->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        // The clock-in cutoff is authored on the shift's starting date. A cutoff
        // earlier than the start (misconfiguration) rolls to the next day.
        $cutoff = $this->at($date, $template->clock_in_cutoff, $tz);
        if ($cutoff->lessThan($start)) {
            $cutoff = $cutoff->addDay();
        }

        $outOpens = $this->at($date, $template->clock_out_opens_at ?? $template->end_time, $tz);
        if ($outOpens->lessThan($start)) {
            $outOpens = $outOpens->addDay();
        }

        $outCloses = $this->at($date, $template->clock_out_closes_at ?? $template->end_time, $tz);
        if ($outCloses->lessThanOrEqualTo($outOpens)) {
            $outCloses = $outCloses->addDay();
        }

        return [
            'start' => $start->utc(),
            'end' => $end->utc(),
            'cutoff' => $cutoff->utc(),
            'out_opens' => $outOpens->utc(),
            'out_closes' => $outCloses->utc(),
        ];
    }

    private function at(string $date, string $time, string $tz): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($date, 0, 10).' '.substr($time, 0, 5), $tz);
    }

    /**
     * Actual lateness in whole minutes between the scheduled start and the
     * server-recorded clock-in, clamped at zero (early arrivals are not early).
     */
    public function lateMinutes(CarbonImmutable $scheduledStart, CarbonImmutable $clockIn): int
    {
        $seconds = $scheduledStart->diffInSeconds($clockIn, false);

        return $seconds <= 0 ? 0 : (int) floor($seconds / 60);
    }

    public function lateBand(int $minutes): ?string
    {
        if ($minutes <= 0) {
            return null;
        }

        foreach ((array) config('attendance.late_bands', []) as $band) {
            if ($minutes >= $band['min'] && $minutes <= $band['max']) {
                return $band['label'];
            }
        }

        return 'Late 30+';
    }
}
