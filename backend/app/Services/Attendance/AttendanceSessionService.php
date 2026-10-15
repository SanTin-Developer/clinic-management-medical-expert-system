<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceScanException;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceSessionToken;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the rotating QR attendance sessions: opening/closing a session, issuing
 * short-lived tokens, validating scans against the authenticated staff account
 * and committing clock mutations.
 */
class AttendanceSessionService
{
    public function __construct(
        private readonly ShiftSchedule $schedule,
        private readonly AttendanceClockService $clock,
        private readonly AttendanceAudit $audit,
    ) {}

    public function open(User $admin, ShiftTemplate $template, string $date, string $action, ?Request $request = null): AttendanceSession
    {
        $date = CarbonImmutable::parse($date)->toDateString();
        $window = $this->schedule->windowFor($template, $date);

        $closesAt = $action === AttendanceSession::ACTION_CLOCK_IN ? $window['cutoff'] : $window['out_closes'];

        if ($closesAt->isPast()) {
            throw ValidationException::withMessages([
                'action' => 'The window for this shift has already closed.',
            ]);
        }

        return DB::transaction(function () use ($admin, $template, $date, $action, $closesAt, $request): AttendanceSession {
            /** @var AttendanceSession|null $session */
            $session = AttendanceSession::query()
                ->where('shift_template_id', $template->id)
                ->whereDate('session_date', $date)
                ->where('action', $action)
                ->lockForUpdate()
                ->first();

            $session ??= new AttendanceSession([
                'shift_template_id' => $template->id,
                'session_date' => $date,
                'action' => $action,
            ]);

            $session->fill([
                'status' => AttendanceSession::STATUS_OPEN,
                'opened_by' => $admin->id,
                'opened_at' => now(),
                'closes_at' => $closesAt,
                'closed_by' => null,
                'closed_at' => null,
                'close_reason' => null,
            ])->save();

            // Invalidate any still-active codes from a previous open.
            $session->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $this->audit->record('attendance.session_opened', $admin->id, $session, [
                'shift_template_id' => $template->id,
                'session_date' => $date,
                'action' => $action,
            ], $request);

            return $session->fresh('shiftTemplate');
        });
    }

    public function close(User $admin, AttendanceSession $session, string $reason = 'manual', ?Request $request = null): AttendanceSession
    {
        return DB::transaction(function () use ($admin, $session, $reason, $request): AttendanceSession {
            if (! $session->isOpen()) {
                return $session;
            }

            $session->fill([
                'status' => AttendanceSession::STATUS_CLOSED,
                'closed_by' => $admin->id,
                'closed_at' => now(),
                'close_reason' => $reason,
            ])->save();

            $session->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $this->audit->record('attendance.session_closed', $admin->id, $session, ['reason' => $reason], $request);

            return $session->fresh('shiftTemplate');
        });
    }

    /**
     * @return array{token: string, expires_at: string, ttl: int, session_id: int, action: string}
     */
    public function currentToken(AttendanceSession $session): array
    {
        $this->assertWindowOpen($session);

        $ttl = (int) config('attendance.qr_ttl', 5);
        $raw = bin2hex(random_bytes(32));
        $expiresAt = now()->addSeconds($ttl);

        AttendanceSessionToken::create([
            'attendance_session_id' => $session->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $raw,
            'expires_at' => $expiresAt->toIso8601String(),
            'ttl' => $ttl,
            'session_id' => $session->id,
            'action' => $session->action,
        ];
    }

    /**
     * Validate a scanned token and apply the clock mutation for the
     * authenticated staff member.
     *
     * @return array{session: AttendanceSession, record: AttendanceRecord}
     */
    public function scan(User $user, string $rawToken, string $action, ?Request $request = null): array
    {
        $token = AttendanceSessionToken::query()
            ->with('session.shiftTemplate')
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $token) {
            throw $this->reject(AttendanceScanException::invalidToken(), $user, null, $request);
        }

        $session = $token->session;

        if ($token->revoked_at) {
            throw $this->reject(AttendanceScanException::revokedToken(), $user, $session, $request);
        }

        if ($token->expires_at->isPast()) {
            throw $this->reject(AttendanceScanException::expiredToken(), $user, $session, $request);
        }

        if (! $session->isOpen()) {
            throw $this->reject(AttendanceScanException::sessionClosed(), $user, $session, $request);
        }

        if ($session->closes_at && $session->closes_at->isPast()) {
            $this->autoClose($session, 'cutoff');
            throw $this->reject(AttendanceScanException::sessionClosed(), $user, $session, $request);
        }

        if ($session->action !== $action) {
            throw $this->reject(AttendanceScanException::wrongAction(), $user, $session, $request);
        }

        $this->assertEligible($user, $session, $request);

        $shift = $this->resolveShift($user, $session);

        if ($this->hasApprovedLeave($user, $session->session_date->toDateString())) {
            throw $this->reject(AttendanceScanException::onLeave(), $user, $session, $request);
        }

        try {
            $record = DB::transaction(function () use ($user, $shift, $session, $action): AttendanceRecord {
                return $action === AttendanceSession::ACTION_CLOCK_IN
                    ? $this->clock->clockIn($user, $shift, $session, CarbonImmutable::now())
                    : $this->clock->clockOut($user, $shift, $session, CarbonImmutable::now());
            });
        } catch (AttendanceScanException $e) {
            throw $this->reject($e, $user, $session, $request);
        }

        $this->audit->record('attendance.scan_accepted', $user->id, $record, [
            'session_id' => $session->id,
            'action' => $action,
        ], $request);

        return ['session' => $session, 'record' => $record];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(AttendanceSession $session): array
    {
        $session->loadMissing('shiftTemplate', 'openedBy:id,name');
        $template = $session->shiftTemplate;
        $date = $session->session_date->toDateString();
        $window = $this->schedule->windowFor($template, $date);

        $assigned = Shift::query()
            ->with('user:id,name,staff_id', 'user.staff:id,user_id,staff_number,department,employment_status')
            ->where('shift_template_id', $template->id)
            ->whereDate('shift_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(fn (Shift $shift): array => [
                'shift_id' => $shift->id,
                'user_id' => $shift->user_id,
                'name' => $shift->user?->name,
                'staff_number' => $shift->user?->staff?->staff_number,
                'department' => $shift->user?->staff?->department,
            ])->values();

        $sessionColumn = $session->action === AttendanceSession::ACTION_CLOCK_IN
            ? 'clock_in_session_id'
            : 'clock_out_session_id';

        $scans = AttendanceRecord::query()
            ->with('user:id,name')
            ->where($sessionColumn, $session->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (AttendanceRecord $record): array => [
                'user_id' => $record->user_id,
                'name' => $record->user?->name,
                'at' => optional(
                    $session->action === AttendanceSession::ACTION_CLOCK_IN ? $record->clock_in : $record->clock_out
                )->toIso8601String(),
                'status' => $record->status,
                'late_minutes' => $record->late_minutes,
            ])->values();

        $scannedIds = $scans->pluck('user_id')->all();
        $unscanned = $assigned->whereNotIn('user_id', $scannedIds)->values();

        return [
            'session' => [
                'id' => $session->id,
                'shift_template_id' => $template->id,
                'shift_name' => $template->name,
                'action' => $session->action,
                'status' => $session->status,
                'session_date' => $date,
                'scheduled_start' => $window['start']->toIso8601String(),
                'scheduled_end' => $window['end']->toIso8601String(),
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closes_at' => $session->closes_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opened_by' => $session->openedBy?->name,
                'ttl' => (int) config('attendance.qr_ttl', 5),
                'poll_seconds' => (int) config('attendance.dashboard_poll_seconds', 3),
            ],
            'assigned' => $assigned,
            'scanned' => $scans,
            'unscanned' => $unscanned,
            'stats' => [
                'assigned' => $assigned->count(),
                'scanned' => $scans->count(),
                'unscanned' => $unscanned->count(),
            ],
        ];
    }

    public function closeExpired(): int
    {
        $count = 0;

        AttendanceSession::query()
            ->where('status', AttendanceSession::STATUS_OPEN)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->orderBy('id')
            ->each(function (AttendanceSession $session) use (&$count): void {
                $this->autoClose($session, 'cutoff');
                $count++;
            });

        return $count;
    }

    private function autoClose(AttendanceSession $session, string $reason): void
    {
        DB::transaction(function () use ($session, $reason): void {
            if (! $session->isOpen()) {
                return;
            }

            $session->fill([
                'status' => AttendanceSession::STATUS_CLOSED,
                'closed_at' => now(),
                'close_reason' => $reason,
            ])->save();

            $session->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $this->audit->record('attendance.session_closed', null, $session, ['reason' => $reason]);
        });
    }

    private function assertWindowOpen(AttendanceSession $session): void
    {
        if (! $session->isOpen()) {
            throw AttendanceScanException::sessionClosed();
        }

        if ($session->closes_at && $session->closes_at->isPast()) {
            $this->autoClose($session, 'cutoff');
            throw AttendanceScanException::sessionClosed();
        }
    }

    private function assertEligible(User $user, AttendanceSession $session, ?Request $request): void
    {
        if (! $user->isActive()) {
            throw $this->reject(AttendanceScanException::notEligible(), $user, $session, $request);
        }

        $staff = $user->staff;

        if (! $staff || ! in_array($staff->employment_status, ['employed', 'probation'], true)) {
            throw $this->reject(AttendanceScanException::notEligible(), $user, $session, $request);
        }
    }

    private function resolveShift(User $user, AttendanceSession $session): Shift
    {
        $shift = Shift::query()
            ->where('user_id', $user->id)
            ->whereDate('shift_date', $session->session_date->toDateString())
            ->where('shift_template_id', $session->shift_template_id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $shift) {
            throw AttendanceScanException::notAssigned();
        }

        return $shift;
    }

    private function hasApprovedLeave(User $user, string $date): bool
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private function reject(AttendanceScanException $exception, User $user, ?AttendanceSession $session, ?Request $request): AttendanceScanException
    {
        $this->audit->record('attendance.scan_rejected', $user->id, $session, [
            'reason' => $exception->reason,
            'session_id' => $session?->id,
        ], $request);

        return $exception;
    }
}
