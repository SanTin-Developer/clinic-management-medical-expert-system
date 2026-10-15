<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();

        $query = LeaveRequest::query()->with(['user:id,name', 'reviewer:id,name']);

        if (! $user->can('leave.approve')) {
            $query->where('user_id', $user->id);
        }

        $base = clone $query;

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('end_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('start_date', '<=', $to);
        }

        $leaves = $query->latest()->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return response()->json(array_merge($leaves->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $validated = $request->validate($this->rules(creating: true));
        $user = $request->user();

        $validated['user_id'] = $user->can('leave.approve') && isset($validated['user_id'])
            ? $validated['user_id']
            : $user->id;

        $validated['days'] = $this->calculateDays($validated['start_date'], $validated['end_date']);
        $validated['status'] = 'pending';

        $leave = LeaveRequest::create($validated);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data' => $leave->fresh(['user:id,name', 'reviewer:id,name']),
        ], 201);
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('view', $leaveRequest);

        return response()->json([
            'data' => $leaveRequest->load(['user:id,name', 'reviewer:id,name']),
        ]);
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        $validated = $request->validate($this->rules());

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $start = $validated['start_date'] ?? $leaveRequest->start_date;
            $end = $validated['end_date'] ?? $leaveRequest->end_date;
            $validated['days'] = $this->calculateDays($start, $end);
        }

        $leaveRequest->update($validated);

        return response()->json([
            'message' => 'Leave request updated successfully.',
            'data' => $leaveRequest->fresh(['user:id,name', 'reviewer:id,name']),
        ]);
    }

    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json([
            'message' => 'Leave request deleted successfully.',
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        return $this->decide($request, $leaveRequest, 'approved');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        return $this->decide($request, $leaveRequest, 'rejected');
    }

    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $leaveRequest->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Leave request cancelled.',
            'data' => $leaveRequest->fresh(['user:id,name', 'reviewer:id,name']),
        ]);
    }

    private function decide(Request $request, LeaveRequest $leaveRequest, string $status): JsonResponse
    {
        if (! $leaveRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $leaveRequest->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return response()->json([
            'message' => $status === 'approved'
                ? 'Leave request approved.'
                : 'Leave request rejected.',
            'data' => $leaveRequest->fresh(['user:id,name', 'reviewer:id,name']),
        ]);
    }

    private function calculateDays(mixed $start, mixed $end): int
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'type' => [$presence, Rule::in(LeaveRequest::TYPES)],
            'start_date' => [$presence, 'date'],
            'end_date' => [$presence, 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
