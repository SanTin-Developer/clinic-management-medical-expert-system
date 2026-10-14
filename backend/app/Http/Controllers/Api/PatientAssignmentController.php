<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\User;
use App\Notifications\PatientAssignedNotification;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PatientAssignmentController extends Controller
{
    public function __construct(private readonly PatientAccessService $access) {}

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

        $base = clone $query;
        $assignments = $query->latest()->paginate(15);

        $stats = [
            'total' => $assignments->total(),
            'active' => (clone $base)->where('status', PatientAssignment::STATUS_ACTIVE)->count(),
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
            'ends_at' => ['nullable', 'date|after_or_equal:starts_at'],
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

        $assignment->update([
            'status' => PatientAssignment::STATUS_ENDED,
            'ends_at' => now(),
            'ended_by' => $request->user()->id,
            'ended_reason' => $validated['ended_reason'] ?? 'Ended by user',
        ]);

        return response()->json([
            'message' => 'Assignment ended successfully.',
            'data' => $assignment->fresh()->load(['nurse', 'patient', 'ward']),
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
}
