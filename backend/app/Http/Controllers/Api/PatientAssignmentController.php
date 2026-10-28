<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AssignmentAuditLog;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\PatientAssignedNotification;
use App\Services\Nursing\AssignmentAudit;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientAssignmentController extends Controller
{
    public function __construct(
        private readonly PatientAccessService $access,
        private readonly AssignmentAudit $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PatientAssignment::class);

        $query = PatientAssignment::query()->with(['nurse', 'patient', 'ward', 'admission', 'assigner']);

        if ($this->access->isRestricted($request->user())) {
            $query->where('nurse_id', $request->user()->id);
        } else {
            if ($nurseId = $request->query('nurse_id')) {
                $query->where('nurse_id', $nurseId);
            }

            if ($patientId = $request->query('patient_id')) {
                $query->where('patient_id', $patientId);
            }

            if ($wardId = $request->query('ward_id')) {
                $query->where('ward_id', $wardId);
            }
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($shift = $request->query('shift')) {
            $query->where('shift', $shift);
        }

        if ($type = $request->query('type')) {
            if ($type === 'patient') {
                $query->whereNotNull('patient_id');
            } elseif ($type === 'ward') {
                $query->whereNotNull('ward_id');
            }
        }

        $base = clone $query;
        $assignments = $query->latest()->paginate(15);

        $stats = [
            'total' => $assignments->total(),
            'active' => (clone $base)->where('status', PatientAssignment::STATUS_ACTIVE)
                ->where('starts_at', '<=', now())
                ->where(function ($inner) {
                    $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->count(),
            'upcoming' => (clone $base)->where('status', PatientAssignment::STATUS_ACTIVE)
                ->where('starts_at', '>', now())
                ->count(),
            'ended' => (clone $base)->where('status', PatientAssignment::STATUS_ENDED)->count(),
        ];

        return response()->json(array_merge($assignments->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PatientAssignment::class);

        $validated = $request->validate([
            'nurse_id' => ['required', 'integer', 'exists:users,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'shift' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', Rule::when($request->filled('starts_at'), ['after_or_equal:starts_at'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['patient_id'] === null && $validated['ward_id'] === null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Either patient_id or ward_id is required.',
                'ward_id' => 'Either patient_id or ward_id is required.',
            ]);
        }

        if ($validated['patient_id'] !== null && $validated['ward_id'] !== null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Specify patient_id or ward_id, not both.',
                'ward_id' => 'Specify patient_id or ward_id, not both.',
            ]);
        }

        $nurse = User::query()->role('Nurse')->findOrFail($validated['nurse_id']);

        $this->assertShiftCompatible($request->user(), $nurse, $validated['shift'] ?? null);

        $assignment = DB::transaction(function () use ($validated, $nurse, $request) {
            $assignment = PatientAssignment::create([
                'nurse_id' => $nurse->id,
                'patient_id' => $validated['patient_id'] ?? null,
                'ward_id' => $validated['ward_id'] ?? null,
                'admission_id' => $validated['admission_id'] ?? null,
                'shift' => $validated['shift'] ?? null,
                'status' => PatientAssignment::STATUS_ACTIVE,
                'starts_at' => $validated['starts_at'] ?? now(),
                'ends_at' => $validated['ends_at'] ?? null,
                'assigned_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->audit->record(
                AssignmentAuditLog::ACTION_CREATED,
                $request->user()->id,
                $assignment,
                [],
                $this->audit->snapshot($assignment),
                null,
                $request,
            );

            return $assignment;
        });

        if ($assignment->patient_id !== null) {
            $patient = Patient::find($assignment->patient_id);
            $nurse->notify(new PatientAssignedNotification(
                assignmentId: $assignment->id,
                patientId: $assignment->patient_id,
                patientName: $patient ? trim("{$patient->first_name} {$patient->last_name}") : 'Patient',
                wardName: $assignment->ward?->name,
                shift: $assignment->shift,
            ));
        }

        return response()->json([
            'message' => 'Assignment created successfully.',
            'data' => $assignment->load(['nurse', 'patient', 'ward', 'admission']),
        ], 201);
    }

    public function end(Request $request, PatientAssignment $assignment): JsonResponse
    {
        $this->authorize('delete', $assignment);

        $validated = $request->validate([
            'ended_reason' => ['nullable', 'string'],
        ]);

        if ($assignment->status !== PatientAssignment::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Only an active assignment can be ended.',
            ]);
        }

        $before = $this->audit->snapshot($assignment);

        $assignment->update([
            'status' => PatientAssignment::STATUS_ENDED,
            'ends_at' => now(),
            'ended_by' => $request->user()->id,
            'ended_reason' => $validated['ended_reason'] ?? 'Ended by user',
        ]);

        $this->audit->record(
            AssignmentAuditLog::ACTION_ENDED,
            $request->user()->id,
            $assignment,
            $before,
            $this->audit->snapshot($assignment->fresh()),
            $validated['ended_reason'] ?? null,
            $request,
        );

        return response()->json([
            'message' => 'Assignment ended successfully.',
            'data' => $assignment->fresh()->load(['nurse', 'patient', 'ward']),
        ]);
    }

    public function update(Request $request, PatientAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        if ($assignment->status !== PatientAssignment::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Only an active assignment can be modified or reassigned.',
            ]);
        }

        $validated = $request->validate([
            'nurse_id' => ['required', 'integer', 'exists:users,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'shift' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', Rule::when($request->filled('starts_at'), ['after_or_equal:starts_at'])],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        if (($validated['patient_id'] ?? null) === null && ($validated['ward_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Either patient_id or ward_id is required.',
                'ward_id' => 'Either patient_id or ward_id is required.',
            ]);
        }

        if (($validated['patient_id'] ?? null) !== null && ($validated['ward_id'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Specify patient_id or ward_id, not both.',
                'ward_id' => 'Specify patient_id or ward_id, not both.',
            ]);
        }

        $nurse = User::query()->role('Nurse')->findOrFail($validated['nurse_id']);

        $identityChanged = $assignment->nurse_id !== $nurse->id
            || ($validated['patient_id'] ?? null) !== $assignment->patient_id
            || ($validated['ward_id'] ?? null) !== $assignment->ward_id
            || ($validated['admission_id'] ?? null) !== $assignment->admission_id;

        $shiftToCheck = array_key_exists('shift', $validated) ? ($validated['shift'] ?? null) : $assignment->shift;
        $this->assertShiftCompatible($request->user(), $nurse, $shiftToCheck);

        $before = $this->audit->snapshot($assignment);
        $reason = $validated['reason'] ?? null;

        if ($identityChanged) {
            $newAssignment = DB::transaction(function () use ($assignment, $validated, $nurse, $before, $reason, $request) {
                $assignment->update([
                    'status' => PatientAssignment::STATUS_ENDED,
                    'ends_at' => now(),
                    'ended_by' => $request->user()->id,
                    'ended_reason' => $reason ?? 'Reassigned',
                ]);

                $this->audit->record(
                    AssignmentAuditLog::ACTION_REASSIGNED,
                    $request->user()->id,
                    $assignment,
                    $before,
                    $this->audit->snapshot($assignment->fresh()),
                    $reason,
                    $request,
                );

                $newAssignment = PatientAssignment::create([
                    'nurse_id' => $nurse->id,
                    'patient_id' => $validated['patient_id'] ?? null,
                    'ward_id' => $validated['ward_id'] ?? null,
                    'admission_id' => $validated['admission_id'] ?? null,
                    'shift' => $validated['shift'] ?? null,
                    'status' => PatientAssignment::STATUS_ACTIVE,
                    'starts_at' => $validated['starts_at'] ?? now(),
                    'ends_at' => $validated['ends_at'] ?? null,
                    'assigned_by' => $request->user()->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->audit->record(
                    AssignmentAuditLog::ACTION_CREATED,
                    $request->user()->id,
                    $newAssignment,
                    [],
                    $this->audit->snapshot($newAssignment),
                    $reason,
                    $request,
                );

                return $newAssignment;
            });

            if ($newAssignment->patient_id !== null) {
                $patient = Patient::find($newAssignment->patient_id);
                $nurse->notify(new PatientAssignedNotification(
                    assignmentId: $newAssignment->id,
                    patientId: $newAssignment->patient_id,
                    patientName: $patient ? trim("{$patient->first_name} {$patient->last_name}") : 'Patient',
                    wardName: $newAssignment->ward?->name,
                    shift: $newAssignment->shift,
                ));
            }

            return response()->json([
                'message' => 'Assignment reassigned successfully.',
                'data' => $newAssignment->load(['nurse', 'patient', 'ward', 'admission']),
            ]);
        }

        $assignment->update([
            'shift' => array_key_exists('shift', $validated) ? ($validated['shift'] ?? null) : $assignment->shift,
            'starts_at' => $validated['starts_at'] ?? $assignment->starts_at,
            'ends_at' => array_key_exists('ends_at', $validated) ? ($validated['ends_at'] ?? null) : $assignment->ends_at,
            'notes' => $validated['notes'] ?? $assignment->notes,
        ]);

        $this->audit->record(
            AssignmentAuditLog::ACTION_UPDATED,
            $request->user()->id,
            $assignment,
            $before,
            $this->audit->snapshot($assignment->fresh()),
            $reason,
            $request,
        );

        return response()->json([
            'message' => 'Assignment updated successfully.',
            'data' => $assignment->fresh()->load(['nurse', 'patient', 'ward', 'admission']),
        ]);
    }

    public function show(PatientAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return response()->json([
            'data' => $assignment->load(['nurse', 'patient', 'ward', 'admission', 'assigner']),
        ]);
    }

    public function myPatients(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PatientAssignment::class);

        $user = $request->user();
        if ($this->access->isRestricted($user)) {
            $ids = $this->access->assignedPatientIds($user);
        } else {
            $ids = PatientAssignment::query()
                ->active()
                ->where('nurse_id', $user->id)
                ->pluck('patient_id')
                ->merge(
                    PatientAssignment::query()
                        ->active()
                        ->where('nurse_id', $user->id)
                        ->whereNotNull('ward_id')
                        ->join('admissions', function ($join) {
                            $join->on('admissions.ward_id', '=', 'patient_assignments.ward_id')
                                ->where('admissions.status', Admission::STATUS_ADMITTED);
                        })
                        ->pluck('admissions.patient_id')
                )
                ->unique();
        }

        $patients = Patient::query()
            ->whereIn('id', $ids)
            ->with(['admissions' => fn (Builder $q) => $q->where('status', Admission::STATUS_ADMITTED)->latest('admitted_at')->limit(1), 'admissions.ward', 'admissions.bed'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return response()->json(['data' => $patients]);
    }

    private function assertShiftCompatible(User $actor, User $nurse, ?string $targetShift): void
    {
        $targetShift = strtolower(trim((string) $targetShift));
        if ($targetShift === '') {
            $targetShift = 'full_time';
        }
        if ($targetShift === 'fulltime' || $targetShift === 'full-time') {
            $targetShift = 'full_time';
        }

        $nurseShift = $this->getActiveShiftTypeForUser($nurse);
        if ($nurseShift === '') {
            return;
        }

        $isMorningTarget = in_array($targetShift, ['day', 'morning', 'am', 'full_time'], true);
        $isAfternoonTarget = in_array($targetShift, ['afternoon', 'pm'], true);
        $isEveningTarget = in_array($targetShift, ['evening'], true);
        $isNightTarget = in_array($targetShift, ['night'], true);

        if (in_array($nurseShift, ['day', 'morning'], true)) {
            if ($isAfternoonTarget || $isEveningTarget || $isNightTarget) {
                throw ValidationException::withMessages([
                    'shift' => 'Morning/day-shift staff cannot be assigned to afternoon, evening, or night shifts.',
                ]);
            }

            return;
        }

        if ($nurseShift === 'afternoon') {
            if ($isMorningTarget || $isEveningTarget || $isNightTarget) {
                throw ValidationException::withMessages([
                    'shift' => 'Afternoon-shift staff cannot be assigned to morning, evening, or night shifts.',
                ]);
            }

            return;
        }

        if ($nurseShift === 'evening') {
            if ($isMorningTarget || $isAfternoonTarget || $isNightTarget) {
                throw ValidationException::withMessages([
                    'shift' => 'Evening-shift staff cannot be assigned to morning, afternoon, or night shifts.',
                ]);
            }

            return;
        }

        if ($nurseShift === 'night') {
            if ($isMorningTarget || $isAfternoonTarget || $isEveningTarget) {
                throw ValidationException::withMessages([
                    'shift' => 'Night-shift staff cannot be assigned to morning, afternoon, or evening shifts.',
                ]);
            }
        }
    }

    private function actorWorksDayShift(User $actor): bool
    {
        if (! $actor->hasRole('Head Nurse')) {
            return false;
        }

        $today = now()->toDateString();
        $shift = Shift::query()
            ->with('shiftTemplate')
            ->where('user_id', $actor->id)
            ->whereDate('shift_date', $today)
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->first();

        if (! $shift) {
            return false;
        }

        $type = strtolower((string) ($shift->type ?? ''));
        $templateType = strtolower((string) ($shift->shiftTemplate?->type ?? ''));

        if (in_array($type, ['day', 'morning', 'am'], true) || in_array($templateType, ['day', 'morning', 'am'], true)) {
            return true;
        }

        if ($shift->start_time) {
            $hour = (int) substr((string) $shift->start_time, 0, 2);
            if ($hour >= 6 && $hour < 14) {
                return true;
            }
        }

        return false;
    }

    private function getActiveShiftTypeForUser(User $nurse): string
    {
        $today = now()->toDateString();
        $shift = Shift::query()
            ->with('shiftTemplate')
            ->where('user_id', $nurse->id)
            ->whereDate('shift_date', $today)
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->first();

        if (! $shift) {
            return '';
        }

        $type = strtolower((string) ($shift->type ?? ''));
        if ($type !== '') {
            if ($type === 'on_call') {
                return '';
            }

            return $type;
        }

        $templateType = strtolower((string) ($shift->shiftTemplate?->type ?? ''));
        if ($templateType !== '') {
            if ($templateType === 'on_call') {
                return '';
            }

            return $templateType;
        }

        if ($shift->start_time) {
            $hour = (int) substr((string) $shift->start_time, 0, 2);
            if ($hour >= 6 && $hour < 14) {
                return 'day';
            }
            if ($hour >= 14 && $hour < 22) {
                return 'evening';
            }

            return 'night';
        }

        return '';
    }
}
