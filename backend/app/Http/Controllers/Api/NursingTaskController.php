<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NursingTask;
use App\Services\Nursing\MedicationAdministrationService;
use App\Services\Nursing\NursingTaskService;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NursingTaskController extends Controller
{
    public function __construct(
        private readonly NursingTaskService $tasks,
        private readonly MedicationAdministrationService $medications,
        private readonly PatientAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NursingTask::class);

        $query = NursingTask::query()->with(['patient', 'assignedNurse', 'admission']);

        $this->access->scopeByPatientColumn($query, $request->user());

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($admissionId = $request->query('admission_id')) {
            $query->where('admission_id', $admissionId);
        }

        if ($assignedNurseId = $request->query('assigned_nurse_id')) {
            $query->where('assigned_nurse_id', $assignedNurseId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('scheduled_at', $date);
        }

        $base = clone $query;
        $tasks = $query->latest('scheduled_at')->paginate(15);

        $stats = [
            'total' => $tasks->total(),
            'pending' => (clone $base)->whereIn('status', [NursingTask::STATUS_PENDING, NursingTask::STATUS_IN_PROGRESS])->count(),
            'completed' => (clone $base)->where('status', NursingTask::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $base)->where('status', NursingTask::STATUS_CANCELLED)->count(),
        ];

        return response()->json(array_merge($tasks->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', NursingTask::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'assigned_nurse_id' => ['nullable', 'integer', 'exists:users,id'],
            'task_type' => ['required', Rule::in($this->taskTypes())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'scheduled_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date|after_or_equal:scheduled_at'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->access->isRestricted($request->user())) {
            abort_unless(
                $this->access->canAccessPatient($request->user(), (int) $validated['patient_id']),
                403,
                'You are not assigned to this patient.',
            );
        }

        $task = $this->tasks->create($validated, $request->user());

        return response()->json([
            'message' => 'Nursing task created successfully.',
            'data' => $task,
        ], 201);
    }

    public function show(NursingTask $nursingTask): JsonResponse
    {
        $this->authorize('view', $nursingTask);

        $nursingTask->load(['patient', 'assignedNurse', 'completer', 'admission', 'vitalSign', 'medicationAdministration.medicine']);

        if ($nursingTask->requiresMedicationAdministration()) {
            $resolved = $this->medications->resolveForTask($nursingTask);
            $resolved?->loadMissing('medicine');

            $nursingTask->setAttribute('scheduled_dose', $resolved === null ? null : [
                'id' => $resolved->id,
                'status' => $resolved->status,
                'dose' => $resolved->dose,
                'route' => $resolved->route,
                'route_detail' => $resolved->route_detail,
                'scheduled_at' => $resolved->scheduled_at?->toIso8601String(),
                'medicine_name' => $resolved->medicine?->name,
            ]);
        }

        return response()->json(['data' => $nursingTask]);
    }

    public function update(Request $request, NursingTask $nursingTask): JsonResponse
    {
        $this->authorize('update', $nursingTask);

        $validated = $request->validate([
            'assigned_nurse_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'task_type' => ['sometimes', Rule::in($this->taskTypes())],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date|after_or_equal:scheduled_at'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($nursingTask->isCompleted()) {
            return response()->json([
                'message' => 'A completed nursing task cannot be modified.',
            ], 422);
        }

        $nursingTask->update($validated);

        return response()->json([
            'message' => 'Nursing task updated successfully.',
            'data' => $nursingTask->fresh()->load(['patient', 'assignedNurse', 'admission']),
        ]);
    }

    public function complete(Request $request, NursingTask $nursingTask): JsonResponse
    {
        $this->authorize('complete', $nursingTask);

        $outcomeOptions = $nursingTask->requiresMedicationAdministration()
            ? MedicationAdministrationService::OUTCOMES
            : NursingTaskService::OUTCOMES;

        $rules = [
            'outcome' => ['nullable', Rule::in($outcomeOptions)],
            'notes' => ['nullable', 'string'],
            'care_provided' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'medication_administration_id' => ['nullable', 'integer', 'exists:medication_administrations,id'],
        ];

        if ($nursingTask->requiresVitalSigns()) {
            $rules = array_merge($rules, [
                'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
                'temperature' => ['nullable', 'numeric', 'between:30,45'],
                'blood_pressure_systolic' => ['nullable', 'integer', 'between:50,250'],
                'blood_pressure_diastolic' => ['nullable', 'integer', 'between:30,150'],
                'heart_rate' => ['nullable', 'integer', 'between:30,250'],
                'respiratory_rate' => ['nullable', 'integer', 'between:5,60'],
                'oxygen_saturation' => ['nullable', 'numeric', 'between:50,100'],
                'weight' => ['nullable', 'numeric', 'between:1,500'],
                'height' => ['nullable', 'numeric', 'between:30,250'],
                'symptoms' => ['nullable', 'string'],
            ]);
        }

        if ($nursingTask->requiresMedicationAdministration()) {
            $rules = array_merge($rules, [
                'dose' => ['nullable', 'string', 'max:255'],
                'route' => ['nullable', Rule::in(MedicationAdministrationService::ROUTES)],
                'route_detail' => ['nullable', 'string', 'max:100'],
            ]);
        }

        $validated = $request->validate($rules);

        $task = $this->tasks->complete($nursingTask, $request->user(), $validated);

        return response()->json([
            'message' => 'Nursing task marked complete.',
            'data' => $task,
        ]);
    }

    public function destroy(NursingTask $nursingTask): JsonResponse
    {
        $this->authorize('delete', $nursingTask);

        if ($nursingTask->isCompleted()) {
            return response()->json([
                'message' => 'A completed nursing task cannot be deleted.',
            ], 422);
        }

        $nursingTask->delete();

        return response()->json(['message' => 'Nursing task deleted successfully.']);
    }

    /**
     * @return array<int, string>
     */
    private function taskTypes(): array
    {
        return [
            NursingTask::TYPE_VITAL_SIGNS,
            NursingTask::TYPE_MEDICATION,
            NursingTask::TYPE_WOUND_CARE,
            NursingTask::TYPE_MOBILITY,
            NursingTask::TYPE_HYGIENE,
            NursingTask::TYPE_OBSERVATION,
            NursingTask::TYPE_PROCEDURE,
            NursingTask::TYPE_EDUCATION,
            NursingTask::TYPE_OTHER,
        ];
    }
}
