<?php

namespace App\Services\Attendance;

use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Backend validation for shift assignments: duplicate prevention, overlap
 * detection (overnight aware) and uncovered-shift reporting.
 */
class ShiftSchedulingService
{
    public function __construct(private readonly ShiftSchedule $schedule) {}

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function shiftInterval(Shift $shift): array
    {
        if ($shift->shiftTemplate) {
            $window = $this->schedule->windowFor($shift->shiftTemplate, $shift->shift_date->toDateString());

            return ['start' => $window['start'], 'end' => $window['end']];
        }

        return $this->instantInterval(
            $shift->shift_date->toDateString(),
            (string) $shift->start_time,
            (string) $shift->end_time,
            (bool) $shift->crosses_midnight,
        );
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function candidateInterval(string $date, string $startTime, string $endTime, bool $crossesMidnight): array
    {
        return $this->instantInterval($date, $startTime, $endTime, $crossesMidnight);
    }

    /**
     * Validate a new/updated assignment. Throws on duplicate or overlap.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $candidate
     */
    public function assertNoConflict(User $user, string $date, array $candidate, ?int $excludeShiftId = null): void
    {
        $this->assertNotInactive($user);
        $this->assertNoDuplicate($user, $date, $excludeShiftId);
        $this->assertNotOnLeave($user, $date);
        $this->assertNoOverlap($user, $date, $candidate, $excludeShiftId);
    }

    public function assertNoDuplicate(User $user, string $date, ?int $excludeShiftId = null): void
    {
        $query = Shift::query()
            ->where('user_id', $user->id)
            ->whereDate('shift_date', $date);

        if ($excludeShiftId) {
            $query->whereKeyNot($excludeShiftId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'shift_date' => 'This staff member already has a shift on that date.',
            ]);
        }
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $candidate
     */
    public function assertNoOverlap(User $user, string $date, array $candidate, ?int $excludeShiftId = null): void
    {
        $day = CarbonImmutable::parse($date);

        $existing = Shift::query()
            ->with('shiftTemplate')
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('shift_date', '>=', $day->subDay()->toDateString())
            ->whereDate('shift_date', '<=', $day->addDay()->toDateString())
            ->when($excludeShiftId, fn ($q) => $q->whereKeyNot($excludeShiftId))
            ->get();

        foreach ($existing as $shift) {
            $interval = $this->shiftInterval($shift);

            if ($candidate['start']->lessThan($interval['end']) && $interval['start']->lessThan($candidate['end'])) {
                throw ValidationException::withMessages([
                    'start_time' => 'This shift overlaps an existing assignment for that staff member.',
                ]);
            }
        }
    }

    public function assertNotInactive(User $user): void
    {
        $staff = $user->staff;

        if ($staff && in_array($staff->employment_status, ['resigned', 'terminated'], true)) {
            throw ValidationException::withMessages([
                'user_id' => 'This staff member is no longer employed.',
            ]);
        }

        if (method_exists($user, 'isActive') && ! $user->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => 'This staff account is not active.',
            ]);
        }
    }

    private function assertNotOnLeave(User $user, string $date): void
    {
        $onLeave = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($onLeave) {
            throw ValidationException::withMessages([
                'shift_date' => 'This staff member is on approved leave for that date.',
            ]);
        }
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function instantInterval(string $date, string $startTime, string $endTime, bool $crossesMidnight): array
    {
        $tz = $this->schedule->timezone();
        $start = CarbonImmutable::parse(substr($date, 0, 10).' '.substr($startTime, 0, 5), $tz);
        $end = CarbonImmutable::parse(substr($date, 0, 10).' '.substr($endTime, 0, 5), $tz);

        if ($crossesMidnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return ['start' => $start->utc(), 'end' => $end->utc()];
    }
}
