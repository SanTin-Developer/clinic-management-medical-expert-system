<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ActivityLog::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'event' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = ActivityLog::query()->with('user:id,name');
        $base = clone $query;

        if (isset($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'].' 23:59:59');
        }

        if ($event = $validated['event'] ?? null) {
            $query->where('event', $event);
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('id')->paginate(15);

        $stats = [
            'total' => $logs->total(),
            'last_24h' => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
            'users' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
            'events' => (clone $base)
                ->whereNotNull('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
        ];

        return response()->json(array_merge($logs->toArray(), ['stats' => $stats]));
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        $this->authorize('view', $activityLog);

        return response()->json([
            'data' => $activityLog->load('user:id,name'),
        ]);
    }
}
