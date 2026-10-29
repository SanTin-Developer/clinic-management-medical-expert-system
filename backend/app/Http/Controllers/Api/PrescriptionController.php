<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Pharmacy\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\Nursing\PatientAccessService;
use App\Services\Pharmacy\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    public function index(Request $request, PatientAccessService $access): JsonResponse
    {
        $this->authorize('viewAny', Prescription::class);

        $base = Prescription::with(['patient', 'doctor', 'items.medicine']);

        // Frontline nurses only ever see prescriptions for assigned patients.
        $access->scopeByPatientColumn($base, $request->user());

        $query = clone $base;

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $prescriptions = $query->latest()->paginate(15);

        $stats = [
            'total' => $prescriptions->total(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'dispensed' => (clone $base)->where('status', 'dispensed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($prescriptions->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Prescription::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string'],
        ]);

        $prescription = DB::transaction(function () use ($validated, $request) {
            $prescription = Prescription::create([
                'prescription_number' => $this->generateNumber(),
                'patient_id' => $validated['patient_id'],
                'doctor_id' => $validated['doctor_id'] ?? $request->user()->id,
                'consultation_id' => $validated['consultation_id'] ?? null,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'issued_at' => now(),
            ]);

            $prescription->items()->createMany($validated['items']);

            return $prescription;
        });

        return response()->json([
            'message' => 'Prescription created successfully.',
            'data' => $prescription->load(['patient', 'doctor', 'items.medicine']),
        ], 201);
    }

    public function show(Prescription $prescription): JsonResponse
    {
        $this->authorize('view', $prescription);

        return response()->json([
            'data' => $prescription->load([
                'patient',
                'doctor',
                'consultation',
                'dispensedBy',
                'items.medicine',
            ]),
        ]);
    }

    public function update(Request $request, Prescription $prescription): JsonResponse
    {
        $this->authorize('update', $prescription);

        if ($prescription->status === 'dispensed' || $prescription->status === 'cancelled') {
            return response()->json([
                'message' => 'A dispensed or cancelled prescription cannot be modified.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.medicine_id' => ['required_with:items', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $prescription) {
            if (array_key_exists('notes', $validated)) {
                $prescription->notes = $validated['notes'];
                $prescription->save();
            }

            if (isset($validated['items'])) {
                $prescription->items()->delete();
                $prescription->items()->createMany($validated['items']);
            }
        });

        return response()->json([
            'message' => 'Prescription updated successfully.',
            'data' => $prescription->fresh()->load(['patient', 'doctor', 'items.medicine']),
        ]);
    }

    public function cancel(Prescription $prescription): JsonResponse
    {
        $this->authorize('update', $prescription);

        if ($prescription->status === 'cancelled') {
            return response()->json([
                'message' => 'Prescription is already cancelled.',
            ], 422);
        }

        if ($prescription->status === 'dispensed') {
            return response()->json([
                'message' => 'A dispensed prescription cannot be cancelled.',
            ], 422);
        }

        $prescription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Prescription cancelled successfully.',
            'data' => $prescription->fresh()->load(['patient', 'doctor', 'items.medicine']),
        ]);
    }

    public function dispense(Request $request, Prescription $prescription, InventoryService $inventory): JsonResponse
    {
        $this->authorize('dispense', $prescription);

        try {
            DB::transaction(function () use ($prescription, $request, $inventory) {
                // Lock and re-check inside the transaction so two concurrent
                // dispense requests cannot both allocate stock.
                $locked = Prescription::query()
                    ->whereKey($prescription->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === 'dispensed') {
                    abort(422, 'Prescription is already dispensed.');
                }

                if ($locked->status === 'cancelled') {
                    abort(422, 'A cancelled prescription cannot be dispensed.');
                }

                $inventory->allocateForItems(
                    $locked->load('items.medicine')->items,
                    $request->user()->id,
                    'Prescription dispensed',
                    $locked->prescription_number,
                );

                $locked->update([
                    'status' => 'dispensed',
                    'dispensed_at' => now(),
                    'dispensed_by' => $request->user()->id,
                ]);
            });
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Prescription dispensed successfully.',
            'data' => $prescription->fresh()->load(['patient', 'doctor', 'items.medicine']),
        ]);
    }

    public function destroy(Prescription $prescription): JsonResponse
    {
        $this->authorize('delete', $prescription);

        $prescription->delete();

        return response()->json([
            'message' => 'Prescription deleted successfully.',
        ]);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'RX-'.random_int(100000, 999999);
        } while (Prescription::where('prescription_number', $number)->exists());

        return $number;
    }
}
