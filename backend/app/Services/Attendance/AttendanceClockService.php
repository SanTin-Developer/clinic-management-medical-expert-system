<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceScanException;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies clock-in/clock-out mutations. Always invoked inside a transaction so
 * the unique(user_id, work_date) constraint is the final guard against
 * duplicate/concurrent submissions.
 */
class AttendanceClockService
{
    public function __construct(
        private readonly ShiftSchedule $schedule,
        private readonly AttendanceAudit $audit,
    ) {}

    public function clockIn(User $user, Shift $shift, AttendanceSession $session, CarbonImmutable $at): AttendanceRecord
    {
        $template = $session->shiftTemplate;
        $workDate = $shift->shift_date->toDateString();
        $window = $this->schedule->windowFor($template, $workDate);

        /** @var AttendanceRecord|null $record */
        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if ($record && $record->clock_in) {
            throw AttendanceScanException::duplicate('You have already clocked in for this shift.');
        }

        $lateMinutes = $this->schedule->lateMinutes($window['start'], $at);

        $record ??= new AttendanceRecord(['user_id' => $user->id, 'work_date' => $workDate]);

        $record->fill([
            'shift_id' => $shift->id,
            'shift_template_id' => $template->id,
            'clock_in' => $at,
            'clock_in_session_id' => $session->id,
            'scheduled_start' => $window['start'],
            'scheduled_end' => $window['end'],
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'late_minutes' => $lateMinutes,
            'late_band' => $this->schedule->lateBand($lateMinutes),
            'clock_in_source' => 'qr',
        ])->save();

        $this->audit->record('attendance.clock_in', $user->id, $record, [
            'shift_id' => $shift->id,
            'session_id' => $session->id,
            'late_minutes' => $lateMinutes,
        ]);

        return $record;
    }

    public function clockOut(User $user, Shift $shift, AttendanceSession $session, CarbonImmutable $at): AttendanceRecord
    {
        $template = $session->shiftTemplate;
        $workDate = $shift->shift_date->toDateString();
        $window = $this->schedule->windowFor($template, $workDate);

        /** @var AttendanceRecord|null $record */
        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if (! $record || ! $record->clock_in) {
            throw AttendanceScanException::conflictingAttendance();
        }

        if ($record->clock_out) {
            throw AttendanceScanException::duplicate('You have already clocked out for this shift.');
        }

        $record->fill([
            'clock_out' => $at,
            'clock_out_session_id' => $session->id,
            'clock_out_source' => 'qr',
            'missing_clock_out' => false,
            'finalized_at' => $at,
            'scheduled_start' => $record->scheduled_start ?? $window['start'],
            'scheduled_end' => $record->scheduled_end ?? $window['end'],
        ])->save();

        $this->audit->record('attendance.clock_out', $user->id, $record, [
            'shift_id' => $shift->id,
            'session_id' => $session->id,
        ]);

        return $record;
    }

    /**
     * Finalize a record that still has no clock-out after its window. Called by
     * the scheduler; never invents a clock-out timestamp.
     */
    public function flagMissingClockOut(AttendanceRecord $record): void
    {
        if ($record->clock_out || $record->missing_clock_out) {
            return;
        }

        DB::transaction(function () use ($record): void {
            $record->forceFill([
                'missing_clock_out' => true,
                'finalized_at' => now(),
            ])->save();

            $this->audit->record('attendance.missing_clock_out', null, $record, [
                'user_id' => $record->user_id,
            ]);
        });
    }
}
