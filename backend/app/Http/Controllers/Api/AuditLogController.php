<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpertEvaluationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpertEvaluationAudit::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ExpertEvaluationAudit::query();

        if (isset($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'].' 23:59:59');
        }

        if ($engineVersion = $request->query('engine_version')) {
            $query->where('engine_version', $engineVersion);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(ExpertEvaluationAudit $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return response()->json([
            'data' => $auditLog,
        ]);
    }
}
