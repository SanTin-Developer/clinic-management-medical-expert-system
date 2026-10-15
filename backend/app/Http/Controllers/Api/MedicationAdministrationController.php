<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicationAdministration;
use App\Services\Nursing\MedicationAdministrationService;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicationAdministrationController extends Controller
{
    public function __construct(
        private readonly MedicationAdministrationService $meds,
        private readonly PatientAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicationAdministration::class);

        $query = MedicationAdministration::query()->with([
            'patient',
            'medicine',
            'prescription',
            'administeredBy',
            'admission',
        ]);

        $this->access->scopeByPatientColumn($query, $request->user());

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($admissionId = $request->query('admission_id')) {
            $query->where('admission_id', $admissionId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('scheduled_at', $date);
        }

        $base = clone $query;
        $records = $query->latest('scheduled_at')->paginate(15);

        $stats = [
            'total' => $records->total(),
            'scheduled' => (clone $base)->where('status', MedicationAdministration::STATUS_SCHEDULED)->count(),
            'administered' => (clone $base)->where('status', MedicationAdministration::STATUS_ADMINISTERED)->count(),
            'held' => (clone $base)->where('status', MedicationAdministration::STATUS_HELD)->count(),
            'refused' => (clone $base)->where('status', MedicationAdministration::STATUS_REFUSED)->count(),
            'missed' => (clone $base)->where('status', MedicationAdministration::STATUS_MISSED)->count(),
            'cancelled' => (clone $base)->where('status', MedicationAdministration::STATUS_CANCELLED)->count(),
        ];

        return response()->json(array_merge($records->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MedicationAdministration::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'nursing_task_id' => ['nullable', 'integer', 'exists:nursing_tasks,id'],
            'prescription_id' => ['nullable', 'integer', 'exists:prescriptions,id'],
            'prescription_item_id' => ['nullable', 'integer', 'exists:prescription_items,id'],
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'ordered_by' => ['nullable', 'integer', 'exists:users,id'],
            'dose' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', Rule::in(MedicationAdministrationService::ROUTES)],
            'route_detail' => ['nullable', 'string', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->access->isRestricted($request->user())) {
            abort_unless(
                $this->access->canAccessPatient($request->user(), (int) $validated['patient_id']),
                403,
                'You are not assigned to this patient.',
            );
        }

        $record = $this->meds->schedule($validated, $request->user());

        return response()->json([
            'message' => 'Medication scheduled successfully.',
            'data' => $record,
        ], 201);
    }

    public function show(MedicationAdministration $medicationAdministration): JsonResponse
    {
        $this->authorize('view', $medicationAdministration);

        return response()->json([
            'data' => $medicationAdministration->load([
                'patient',
                'admission',
                'prescription',
                'prescriptionItem',
                'medicine',
                'orderedBy',
                'administeredBy',
            ]),
        ]);
    }

    public function administer(Request $request, MedicationAdministration $medicationAdministration): JsonResponse
    {
        $this->authorize('administer', $medicationAdministration);

        $validated = $request->validate([
            'outcome' => ['nullable', Rule::in(MedicationAdministrationService::OUTCOMES)],
            'dose' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', Rule::in(MedicationAdministrationService::ROUTES)],
            'route_detail' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = $this->meds->administer($medicationAdministration, $request->user(), $validated);

        return response()->json([
            'message' => 'Medication administration recorded successfully.',
            'data' => $record,
        ]);
    }

    public function updateStatus(Request $request, MedicationAdministration $medicationAdministration): JsonResponse
    {
        $this->authorize('administer', $medicationAdministration);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                MedicationAdministration::STATUS_HELD,
                MedicationAdministration::STATUS_REFUSED,
                MedicationAdministration::STATUS_MISSED,
                MedicationAdministration::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string'],
        ]);

        $record = $this->meds->updateStatus(
            $medicationAdministration,
            $request->user(),
            $validated['status'],
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Medication administration status updated.',
            'data' => $record,
        ]);
    }

    public function destroy(MedicationAdministration $medicationAdministration): JsonResponse
    {
        $this->authorize('delete', $medicationAdministration);

        $medicationAdministration->delete();

        return response()->json([
            'message' => 'Medication administration deleted successfully.',
        ]);
    }
}
