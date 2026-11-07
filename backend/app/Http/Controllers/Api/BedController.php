<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Bed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Bed::class);

        $query = Bed::query()->with(['room.ward']);

        if ($roomId = $request->query('room_id')) {
            $query->where('room_id', $roomId);
        }

        if ($wardId = $request->query('ward_id')) {
            $query->whereHas('room', fn ($q) => $q->where('ward_id', $wardId));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $beds = $query->orderBy('room_id')->orderBy('label')->paginate(50);

        $stats = [
            'total' => Bed::query()->count(),
            'available' => Bed::query()->where('status', Bed::STATUS_AVAILABLE)->count(),
            'occupied' => Bed::query()->where('status', Bed::STATUS_OCCUPIED)->count(),
            'cleaning' => Bed::query()->where('status', Bed::STATUS_CLEANING)->count(),
            'maintenance' => Bed::query()->where('status', Bed::STATUS_MAINTENANCE)->count(),
            'reserved' => Bed::query()->where('status', Bed::STATUS_RESERVED)->count(),
        ];

        return response()->json(array_merge($beds->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Bed::class);

        $validated = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'label' => [
                'required', 'string', 'max:30',
                Rule::unique('beds', 'label')->where('room_id', $request->input('room_id')),
            ],
            'status' => ['sometimes', Rule::in($this->manageableStatuses())],
            'notes' => ['nullable', 'string'],
        ]);

        $bed = Bed::create($validated);

        return response()->json([
            'message' => 'Bed created successfully.',
            'data' => $bed->load('room.ward'),
        ], 201);
    }

    public function show(Bed $bed): JsonResponse
    {
        $this->authorize('view', $bed);

        return response()->json([
            'data' => $bed->load(['room.ward', 'admissions.patient']),
        ]);
    }

    public function update(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('update', $bed);

        $validated = $request->validate([
            'label' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('beds', 'label')
                    ->where('room_id', $request->input('room_id', $bed->room_id))
                    ->ignore($bed->id),
            ],
            'status' => ['sometimes', Rule::in($this->manageableStatuses())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Occupancy is driven exclusively by the admission workflow.
        if (array_key_exists('status', $validated) && $bed->status === Bed::STATUS_OCCUPIED) {
            return response()->json([
                'message' => 'An occupied bed can only be freed by discharging or transferring the patient.',
            ], 422);
        }

        $bed->update($validated);

        return response()->json([
            'message' => 'Bed updated successfully.',
            'data' => $bed->fresh()->load('room.ward'),
        ]);
    }

    public function updateStatus(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('update', $bed);

        $validated = $request->validate([
            'status' => ['required', Rule::in($this->manageableStatuses())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Occupancy is driven exclusively by the admission workflow.
        if ($bed->status === Bed::STATUS_OCCUPIED) {
            return response()->json([
                'message' => 'An occupied bed can only be freed by discharging or transferring the patient.',
            ], 422);
        }

        $bed->update($validated);

        return response()->json([
            'message' => 'Bed status updated successfully.',
            'data' => $bed->fresh()->load('room.ward'),
        ]);
    }

    public function destroy(Bed $bed): JsonResponse
    {
        $this->authorize('delete', $bed);

        $hasActiveAdmission = $bed->admissions()
            ->where('status', Admission::STATUS_ADMITTED)
            ->exists();

        if ($hasActiveAdmission) {
            return response()->json([
                'message' => 'A bed with an active admission cannot be deleted.',
            ], 422);
        }

        $bed->delete();

        return response()->json(['message' => 'Bed deleted successfully.']);
    }

    /**
     * Statuses an operator may set directly. `occupied` is intentionally
     * excluded: it is only ever set by admitting or transferring a patient.
     *
     * @return array<int, string>
     */
    private function manageableStatuses(): array
    {
        return [
            Bed::STATUS_AVAILABLE,
            Bed::STATUS_RESERVED,
            Bed::STATUS_CLEANING,
            Bed::STATUS_MAINTENANCE,
        ];
    }
}
