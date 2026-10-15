<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $user = $request->user();
        $canManage = $user->can('scheduling.manage');

        $query = Shift::query()->with('user:id,name');

        if (! $canManage) {
            $query->where('user_id', $user->id);
        } elseif ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('shift_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('shift_date', '<=', $to);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
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

        $shifts = $query
            ->orderByDesc('shift_date')
            ->orderBy('start_time')
            ->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('shift_date', now()->toDateString())->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        return response()->json(array_merge($shifts->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['sometimes', Rule::in(Shift::TYPES)],
            'status' => ['sometimes', Rule::in(Shift::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shift = Shift::create([
            'user_id' => $validated['user_id'],
            'shift_date' => $validated['shift_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'type' => $validated['type'] ?? 'day',
            'status' => $validated['status'] ?? 'scheduled',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Shift scheduled.',
            'data' => $shift->load('user:id,name'),
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);

        return response()->json(['data' => $shift->load('user:id,name')]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'shift_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'type' => ['sometimes', Rule::in(Shift::TYPES)],
            'status' => ['sometimes', Rule::in(Shift::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shift->update($validated);

        return response()->json([
            'message' => 'Shift updated.',
            'data' => $shift->fresh('user:id,name'),
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);

        $shift->delete();

        return response()->json(['message' => 'Shift removed.']);
    }
}
