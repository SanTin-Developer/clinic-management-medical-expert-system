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
            'engine_version' => ['nullable', 'string', 'max:50'],
            'validated' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = ExpertEvaluationAudit::query();
        $base = clone $query;

        if (isset($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'].' 23:59:59');
        }

        if ($engineVersion = $validated['engine_version'] ?? null) {
            $query->where('engine_version', $engineVersion);
        }

        if ($request->has('validated')) {
            $query->where('is_clinically_validated', $request->boolean('validated'));
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($builder) use ($search) {
                $builder->where('matched_rule_codes', 'like', "%{$search}%")
                    ->orWhere('symptom_ids', 'like', "%{$search}%")
                    ->orWhere('engine_version', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(15);

        $stats = [
            'total' => $logs->total(),
            'last_24h' => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
            'validated' => (clone $base)->where('is_clinically_validated', true)->count(),
            'engine_versions' => (clone $base)
                ->whereNotNull('engine_version')
                ->distinct()
                ->orderBy('engine_version')
                ->pluck('engine_version'),
        ];

        return response()->json(array_merge($logs->toArray(), ['stats' => $stats]));
    }

    public function show(ExpertEvaluationAudit $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return response()->json([
            'data' => $auditLog,
        ]);
    }
}
