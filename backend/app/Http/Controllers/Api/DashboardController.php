<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Single analytics endpoint backing the dashboard charts. Served from the
     * cached aggregate views; refreshed in realtime via the `dashboard`
     * broadcast channel when underlying records change.
     */
    public function analytics(Request $request, DashboardMetricsService $metrics): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403, 'This action is unauthorized.');

        return response()->json([
            'data' => $metrics->analytics(),
        ]);
    }
}
