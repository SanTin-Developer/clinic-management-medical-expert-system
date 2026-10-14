<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Room::class);

        $query = Room::query()->with(['ward'])->withCount('beds');

        if ($wardId = $request->query('ward_id')) {
            $query->where('ward_id', $wardId);
        }

        $rooms = $query->orderBy('room_number')->paginate(15);

        return response()->json($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Room::class);

        $validated = $request->validate([
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'room_number' => [
                'required', 'string', 'max:30',
                Rule::unique('rooms', 'room_number')->where('ward_id', $request->input('ward_id')),
            ],
            'room_type' => ['nullable', Rule::in($this->roomTypes())],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'price_per_day' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Room created successfully.',
            'data' => $room->load('ward'),
        ], 201);
    }

    public function show(Room $room): JsonResponse
    {
        $this->authorize('view', $room);

        return response()->json([
            'data' => $room->load(['ward', 'beds']),
        ]);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $this->authorize('update', $room);

        $validated = $request->validate([
            'room_number' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('rooms', 'room_number')
                    ->where('ward_id', $request->input('ward_id', $room->ward_id))
                    ->ignore($room->id),
            ],
            'room_type' => ['sometimes', Rule::in($this->roomTypes())],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'price_per_day' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully.',
            'data' => $room->fresh()->load('ward'),
        ]);
    }

    public function destroy(Room $room): JsonResponse
    {
        $this->authorize('delete', $room);

        if ($room->beds()->where('status', 'occupied')->exists()) {
            return response()->json([
                'message' => 'A room with occupied beds cannot be deleted.',
            ], 422);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted successfully.']);
    }

    /**
     * @return array<int, string>
     */
    private function roomTypes(): array
    {
        return [
            Room::TYPE_GENERAL,
            Room::TYPE_PRIVATE,
            Room::TYPE_SEMI_PRIVATE,
            Room::TYPE_ISOLATION,
        ];
    }
}
