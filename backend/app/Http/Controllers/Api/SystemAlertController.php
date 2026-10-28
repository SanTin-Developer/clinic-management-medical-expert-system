<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemAlertIndexRequest;
use App\Models\SystemAlert;
use App\Services\Alerts\SystemAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemAlertController extends Controller
{
    public function __construct(private readonly SystemAlertService $alerts)
    {
    }

    public function index(SystemAlertIndexRequest $request): JsonResponse
    {
        $alerts = $this->alerts->listForUser($request->user(), $request->validated());

        return response()->json($alerts->through(fn (SystemAlert $alert) => $this->serialize($alert)));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SystemAlert::class);

        return response()->json([
            'unread_count' => $this->alerts->unreadCountForUser($request->user()),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SystemAlert::class);

        return response()->json([
            'data' => $this->alerts->summaryForUser($request->user()),
        ]);
    }

    public function markRead(Request $request, SystemAlert $systemAlert): JsonResponse
    {
        $this->authorize('view', $systemAlert);

        $alert = $this->alerts->markRead($systemAlert);

        return response()->json([
            'message' => 'Alert marked as read.',
            'data' => $this->serialize($alert),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SystemAlert::class);

        $count = $this->alerts->markAllRead($request->user());

        return response()->json([
            'message' => 'All visible alerts marked as read.',
            'count' => $count,
        ]);
    }

    public function acknowledge(Request $request, SystemAlert $systemAlert): JsonResponse
    {
        $this->authorize('update', $systemAlert);

        $alert = $this->alerts->acknowledge($systemAlert, $request->user());

        return response()->json([
            'message' => 'Alert acknowledged.',
            'data' => $this->serialize($alert),
        ]);
    }

    public function resolve(Request $request, SystemAlert $systemAlert): JsonResponse
    {
        $this->authorize('update', $systemAlert);

        $alert = $this->alerts->resolve($systemAlert, $request->user());

        return response()->json([
            'message' => 'Alert resolved.',
            'data' => $this->serialize($alert),
        ]);
    }

    private function serialize(SystemAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'source_key' => $alert->source_key,
            'category' => $alert->category,
            'patient_id' => $alert->patient_id,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'module' => $alert->module,
            'url' => $alert->url,
            'data' => $alert->data,
            'status' => $alert->status,
            'read_at' => $alert->read_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'created_at' => $alert->created_at?->toIso8601String(),
            'updated_at' => $alert->updated_at?->toIso8601String(),
        ];
    }
}
