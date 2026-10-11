<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Prescription;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrescriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Prescription::class);

        $query = Prescription::with(['patient', 'doctor', 'items.medicine']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return response()->json($query->latest()->paginate(15));
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

    public function dispense(Request $request, Prescription $prescription): JsonResponse
    {
        $this->authorize('dispense', $prescription);

        if ($prescription->status === 'dispensed') {
            return response()->json([
                'message' => 'Prescription is already dispensed.',
            ], 422);
        }

        if ($prescription->status === 'cancelled') {
            return response()->json([
                'message' => 'A cancelled prescription cannot be dispensed.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($prescription, $request) {
                $this->allocateStock(
                    $prescription->load('items.medicine'),
                    $request->user()->id
                );

                $prescription->update([
                    'status' => 'dispensed',
                    'dispensed_at' => now(),
                    'dispensed_by' => $request->user()->id,
                ]);
            });
        } catch (RuntimeException $e) {
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

    /**
     * Deduct the prescribed quantities from available inventory batches (FIFO
     * by expiry date) and record a stock-out movement for each batch used.
     */
    private function allocateStock(Prescription $prescription, int $userId): void
    {
        foreach ($prescription->items as $item) {
            $remaining = $item->quantity;

            $batches = InventoryItem::where('medicine_id', $item->medicine_id)
                ->where('quantity', '>', 0)
                ->orderByRaw('expiration_date is null')
                ->orderBy('expiration_date')
                ->orderBy('received_date')
                ->lockForUpdate()
                ->get();

            if ($batches->sum('quantity') < $remaining) {
                throw new RuntimeException(
                    "Insufficient stock for {$item->medicine->name}."
                );
            }

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->quantity, $remaining);

                $batch->quantity -= $take;
                $batch->save();

                $remaining -= $take;

                StockMovement::create([
                    'medicine_id' => $item->medicine_id,
                    'inventory_item_id' => $batch->id,
                    'user_id' => $userId,
                    'type' => 'out',
                    'quantity' => $take,
                    'reason' => 'Prescription dispensed',
                    'reference' => $prescription->prescription_number,
                ]);
            }
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'RX-'.random_int(100000, 999999);
        } while (Prescription::where('prescription_number', $number)->exists());

        return $number;
    }
}
