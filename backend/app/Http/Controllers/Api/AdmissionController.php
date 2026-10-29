<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Bed;
use App\Services\Nursing\AdmissionService;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionService $admissions,
        private readonly PatientAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Admission::class);

        $query = Admission::with(['patient', 'ward', 'bed', 'attendingDoctor']);

        // Frontline nurses only ever see admissions for assigned patients.
        $this->access->scopeByPatientColumn($query, $request->user());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($wardId = $request->query('ward_id')) {
            $query->where('ward_id', $wardId);
        }

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $base = clone $query;
        $admissions = (clone $query)->latest('admitted_at')->paginate(15);

        $stats = [
            'total' => $admissions->total(),
            'admitted' => (clone $base)->where('status', Admission::STATUS_ADMITTED)->count(),
            'discharged' => (clone $base)->where('status', Admission::STATUS_DISCHARGED)->count(),
            'cancelled' => (clone $base)->where('status', Admission::STATUS_CANCELLED)->count(),
        ];

        return response()->json(array_merge($admissions->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Admission::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'attending_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string'],
            'diagnosis_summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'admitted_at' => ['nullable', 'date'],
        ]);

        if ($this->access->isRestricted($request->user())) {
            abort_unless(
                $this->access->canAccessPatient($request->user(), (int) $validated['patient_id']),
                403,
                'You are not assigned to this patient.',
            );
        }

        $admission = $this->admissions->admit($validated, $request->user());

        return response()->json([
            'message' => 'Patient admitted successfully.',
            'data' => $admission,
        ], 201);
    }

    public function show(Admission $admission): JsonResponse
    {
        $this->authorize('view', $admission);

        return response()->json([
            'data' => $admission->load([
                'patient',
                'ward',
                'bed.room',
                'attendingDoctor',
                'assignments.nurse',
                'nursingTasks',
                'medicationAdministrations.medicine',
            ]),
        ]);
    }

    public function discharge(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('discharge', $admission);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'discharged_at' => ['nullable', 'date'],
        ]);

        $admission = $this->admissions->discharge($admission, $request->user(), $validated);

        return response()->json([
            'message' => 'Patient discharged successfully.',
            'data' => $admission,
        ]);
    }

    public function transfer(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);

        $validated = $request->validate([
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $bed = Bed::findOrFail($validated['bed_id']);
        $admission = $this->admissions->transfer($admission, $bed, $request->user(), $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Patient transferred successfully.',
            'data' => $admission,
        ]);
    }

    public function cancel(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $admission = $this->admissions->cancel($admission, $request->user(), $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Admission cancelled successfully.',
            'data' => $admission,
        ]);
    }

    public function update(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);

        $validated = $request->validate([
            'attending_doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'diagnosis_summary' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            // Status transitions must go through discharge/transfer/cancel so
            // bed occupancy, nurse assignments, tasks and MAR stay in sync.
        ]);

        $admission->update($validated);

        return response()->json([
            'message' => 'Admission updated successfully.',
            'data' => $admission->fresh()->load(['patient', 'ward', 'bed', 'attendingDoctor']),
        ]);
    }
}
