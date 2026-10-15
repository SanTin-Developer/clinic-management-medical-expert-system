<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $user = $request->user();
        $canManage = $user->can('attendance.manage');

        $query = AttendanceRecord::query()->with('user:id,name');

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

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%");
            });
        }

        $base = clone $query;

        if ($status = $request->query('status')) {
            $query->where('status', $status);
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
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Attendance record created.',
            'data' => $record->load('user:id,name'),
        ], 201);
    }

    public function show(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('view', $attendanceRecord);

        return response()->json(['data' => $attendanceRecord->load('user:id,name', 'approver:id,name')]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validate([
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['sometimes', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendanceRecord->update($validated);

        return response()->json([
            'message' => 'Attendance record updated.',
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

        $record->update(['clock_out' => now()]);

        return response()->json([
            'message' => 'Clocked out.',
            'data' => $record->fresh('user:id,name'),
        ]);
    }
}
