<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\Attendance\AttendanceAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecordController extends Controller
{
    /**
     * Fields whose change is a correction and therefore requires a reason and
     * leaves an audit trail.
     *
     * @var list<string>
     */
    private const CORRECTABLE = ['clock_in', 'clock_out', 'status'];

    public function __construct(private readonly AttendanceAudit $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $user = $request->user();
        $canManage = $user->can('attendance.manage');

        $query = AttendanceRecord::query()->with([
            'user:id,name,staff_id',
            'user.staff:user_id,staff_number,department,job_title',
            'shiftTemplate:id,name,type',
        ]);

        if (! $canManage) {
            $query->where('user_id', $user->id);
        } elseif ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('work_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('work_date', '<=', $to);
        }

        if ($department = $request->query('department')) {
            $query->whereHas('user.staff', fn ($builder) => $builder->where('department', $department));
        }

        if ($templateId = $request->query('shift_template_id')) {
            $query->where('shift_template_id', $templateId);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%");
            });
        }

        $base = clone $query;

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->has('missing_clock_out')) {
            $query->where('missing_clock_out', $request->boolean('missing_clock_out'));
        }

        $records = $query
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in')
            ->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'present' => (clone $base)->where('status', 'present')->count(),
            'late' => (clone $base)->where('status', 'late')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'on_leave' => (clone $base)->where('status', 'on_leave')->count(),
            'holiday' => (clone $base)->where('status', 'holiday')->count(),
            'incomplete' => (clone $base)->whereNotNull('clock_in')->whereNull('clock_out')->count(),
            'missing_clock_out' => (clone $base)->where('missing_clock_out', true)->count(),
        ];

        return response()->json(array_merge($records->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AttendanceRecord::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['sometimes', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $exists = AttendanceRecord::query()
            ->where('user_id', $validated['user_id'])
            ->whereDate('work_date', $validated['work_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'work_date' => 'An attendance record already exists for that staff member on that date.',
            ]);
        }

        $record = AttendanceRecord::create([
            'user_id' => $validated['user_id'],
            'work_date' => $validated['work_date'],
            'clock_in' => $validated['clock_in'] ?? null,
            'clock_out' => $validated['clock_out'] ?? null,
            'status' => $validated['status'] ?? 'present',
            'clock_in_source' => isset($validated['clock_in']) ? 'manual' : null,
            'clock_out_source' => isset($validated['clock_out']) ? 'manual' : null,
            'notes' => $validated['notes'] ?? null,
            'corrected_by' => $request->user()->id,
            'corrected_at' => now(),
        ]);

        $record->corrections()->create([
            'corrected_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'old_values' => null,
            'new_values' => $record->only(self::CORRECTABLE),
        ]);

        $this->audit->record('attendance.manual_entry', $request->user()->id, $record, [
            'reason' => $validated['reason'],
        ], $request);

        return response()->json([
            'message' => 'Attendance record created.',
            'data' => $record->load('user:id,name'),
        ], 201);
    }

    public function show(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('view', $attendanceRecord);

        return response()->json([
            'data' => $attendanceRecord->load([
                'user:id,name,staff_id',
                'user.staff:user_id,staff_number,department,job_title',
                'approver:id,name',
                'shiftTemplate:id,name,type',
                'corrections.correctedBy:id,name',
            ]),
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validate([
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['sometimes', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->applyCorrection($request, $attendanceRecord, $validated);

        return response()->json([
            'message' => 'Attendance record updated.',
            'data' => $attendanceRecord->fresh('user:id,name'),
        ]);
    }

    public function correct(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('correct', $attendanceRecord);

        $validated = $request->validate([
            'clock_in' => ['sometimes', 'nullable', 'date'],
            'clock_out' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->applyCorrection($request, $attendanceRecord, $validated, forceAudit: true);

        return response()->json([
            'message' => 'Attendance record corrected.',
            'data' => $attendanceRecord->fresh('user:id,name'),
        ]);
    }

    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json(['message' => 'Attendance record deleted.']);
    }

    public function approve(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('approve', $attendanceRecord);

        $attendanceRecord->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance record approved.',
            'data' => $attendanceRecord->fresh('user:id,name', 'approver:id,name'),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $this->authorize('clock', AttendanceRecord::class);

        $record = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        return response()->json(['data' => $record]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $this->authorize('clock', AttendanceRecord::class);

        $today = now()->toDateString();

        $record = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', $today)
            ->first();

        if ($record && $record->clock_in) {
            throw ValidationException::withMessages([
                'clock_in' => 'You have already clocked in today.',
            ]);
        }

        $record ??= new AttendanceRecord(['user_id' => $request->user()->id, 'work_date' => $today]);

        $record->fill([
            'clock_in' => now(),
            'clock_in_source' => 'manual',
            'status' => 'present',
        ])->save();

        return response()->json([
            'message' => 'Clocked in.',
            'data' => $record->load('user:id,name'),
        ]);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $this->authorize('clock', AttendanceRecord::class);

        $record = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        if (! $record || ! $record->clock_in) {
            throw ValidationException::withMessages([
                'clock_out' => 'You have not clocked in today.',
            ]);
        }

        if ($record->clock_out) {
            throw ValidationException::withMessages([
                'clock_out' => 'You have already clocked out today.',
            ]);
        }

        $record->update([
            'clock_out' => now(),
            'clock_out_source' => 'manual',
        ]);

        return response()->json([
            'message' => 'Clocked out.',
            'data' => $record->fresh('user:id,name'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', AttendanceRecord::class);

        $query = AttendanceRecord::query()->with([
            'user:id,name,staff_id',
            'user.staff:user_id,staff_number,department',
            'shiftTemplate:id,name',
        ]);

        if ($from = $request->query('from')) {
            $query->whereDate('work_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('work_date', '<=', $to);
        }

        if ($department = $request->query('department')) {
            $query->whereHas('user.staff', fn ($builder) => $builder->where('department', $department));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $rows = $query->orderByDesc('work_date')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Staff', 'Staff Number', 'Department', 'Date', 'Shift',
                'Scheduled Start', 'Scheduled End', 'Clock In', 'Clock Out',
                'Late Minutes', 'Late Band', 'Hours', 'Status', 'Missing Clock Out',
            ], ',', '"', '');

            foreach ($rows as $record) {
                fputcsv($handle, [
                    $record->user?->name,
                    $record->user?->staff?->staff_number,
                    $record->user?->staff?->department,
                    $record->work_date?->toDateString(),
                    $record->shiftTemplate?->name,
                    $record->scheduled_start?->toDateTimeString(),
                    $record->scheduled_end?->toDateTimeString(),
                    $record->clock_in?->toDateTimeString(),
                    $record->clock_out?->toDateTimeString(),
                    $record->late_minutes,
                    $record->late_band,
                    $record->hours_worked,
                    $record->status,
                    $record->missing_clock_out ? 'yes' : 'no',
                ], ',', '"', '');
            }

            fclose($handle);
        }, 'attendance-report.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Apply validated changes, requiring a reason for timestamp/status changes
     * and recording an immutable correction entry.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyCorrection(Request $request, AttendanceRecord $record, array $validated, bool $forceAudit = false): void
    {
        $old = $record->only(self::CORRECTABLE);
        $changed = [];

        foreach ($validated as $key => $value) {
            if ($key === 'reason') {
                continue;
            }

            if (in_array($key, self::CORRECTABLE, true) && (string) $record->getAttribute($key) !== (string) $value) {
                $changed[$key] = true;
            }
        }

        $reason = $validated['reason'] ?? null;

        if ($changed && ! $reason) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when changing clock times or status.',
            ]);
        }

        $update = $validated;
        unset($update['reason']);

        if ($changed) {
            $update['corrected_by'] = $request->user()->id;
            $update['corrected_at'] = now();

            if (array_key_exists('clock_in', $update) && $update['clock_in'] !== null) {
                $update['clock_in_source'] = 'manual';
            }
            if (array_key_exists('clock_out', $update) && $update['clock_out'] !== null) {
                $update['clock_out_source'] = 'manual';
            }
        }

        $record->update($update);

        if ($changed || $forceAudit) {
            $record->corrections()->create([
                'corrected_by' => $request->user()->id,
                'reason' => $reason,
                'old_values' => $old,
                'new_values' => $record->only(self::CORRECTABLE),
            ]);

            $this->audit->record('attendance.corrected', $request->user()->id, $record, [
                'changed' => array_keys($changed),
                'reason' => $reason,
            ], $request);
        }
    }
}
