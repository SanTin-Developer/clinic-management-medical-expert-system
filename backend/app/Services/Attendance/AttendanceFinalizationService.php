<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\ClinicHoliday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;

/**
 * Finalizes attendance after a shift's clock-in window closes: creates absence
 * records for scheduled staff who never clocked in (respecting holidays, days
 * off and approved leave) and flags clock-ins that never clocked out.
 *
 * Every operation is idempotent so repeated scheduler runs do not duplicate
 * records.
 */
class AttendanceFinalizationService
{
    public function __construct(
        private readonly ShiftSchedule $schedule,
        private readonly AttendanceClockService $clock,
        private readonly AttendanceAudit $audit,
    ) {}

    public function isHoliday(string $date): bool
    {
        $day = CarbonImmutable::parse($date);

        return ClinicHoliday::query()->whereDate('holiday_date', $date)->exists()
            || ClinicHoliday::query()
                ->where('is_recurring', true)
                ->whereMonth('holiday_date', $day->month)
                ->whereDay('holiday_date', $day->day)
                ->exists();
    }

    /**
     * Finalize every active template whose cutoff has passed today, plus a
     * catch-up window of previous days. Returns the number of records touched.
     */
    public function finalizeDue(CarbonImmutable $now, int $lookbackDays = 7): int
    {
        $tz = $this->schedule->timezone();
        $today = $now->setTimezone($tz)->toDateString();

        $templates = ShiftTemplate::query()->where('is_active', true)->get();
        $touched = 0;

        foreach ($templates as $template) {
            $window = $this->schedule->windowFor($template, $today);

            if ($now->greaterThanOrEqualTo($window['cutoff'])) {
                $touched += $this->finalizeTemplate($template, $today);
            }

            for ($i = 1; $i <= $lookbackDays; $i++) {
                $touched += $this->finalizeTemplate($template, CarbonImmutable::parse($today)->subDays($i)->toDateString());
            }
        }

        return $touched;
    }

    public function finalizeTemplate(ShiftTemplate $template, string $date): int
    {
        $shifts = Shift::query()
            ->with('user.staff')
            ->where('shift_template_id', $template->id)
            ->whereDate('shift_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($shifts->isEmpty()) {
            return 0;
        }

        $window = $this->schedule->windowFor($template, $date);
        $holiday = $this->isHoliday($date);
        $touched = 0;

        foreach ($shifts as $shift) {
            $user = $shift->user;

            if (! $user) {
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $date)
                ->first();

            if ($record && $record->clock_in) {
                if (! $record->clock_out && $this->scheduleEnded($window, CarbonImmutable::now())) {
                    $this->clock->flagMissingClockOut($record);
                    $touched++;
                }

                continue;
            }

            // Respect clinic holidays and days off: never mark absent.
            if ($holiday) {
                continue;
            }

            if ($this->onApprovedLeave($user->id, $date)) {
                if (! $record) {
                    AttendanceRecord::create([
                        'user_id' => $user->id,
                        'shift_id' => $shift->id,
                        'shift_template_id' => $template->id,
                        'work_date' => $date,
                        'status' => 'on_leave',
                        'scheduled_start' => $window['start'],
                        'scheduled_end' => $window['end'],
                    ]);
                    $touched++;
                } elseif ($record->status !== 'on_leave') {
                    $record->update(['status' => 'on_leave']);
                    $touched++;
                }

                continue;
            }

            if ($record && $record->status === 'absent') {
                continue; // already finalized
            }

            if ($record) {
                $record->update(['status' => 'absent']);
            } else {
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'shift_id' => $shift->id,
                    'shift_template_id' => $template->id,
                    'work_date' => $date,
                    'status' => 'absent',
                    'scheduled_start' => $window['start'],
                    'scheduled_end' => $window['end'],
                ]);
            }

            $this->audit->record('attendance.absence_finalized', null, $shift, [
                'user_id' => $user->id,
                'date' => $date,
                'shift_template_id' => $template->id,
            ]);

            $touched++;
        }

        return $touched;
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable, cutoff: CarbonImmutable, out_opens: CarbonImmutable, out_closes: CarbonImmutable}  $window
     */
    private function scheduleEnded(array $window, CarbonImmutable $now): bool
    {
        return $now->greaterThan($window['out_closes']);
    }

    private function onApprovedLeave(int $userId, string $date): bool
    {
        return LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
