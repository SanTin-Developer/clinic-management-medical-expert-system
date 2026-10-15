<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AttendanceScanException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validates a scanned QR token for the authenticated staff member. The staff
 * identity always comes from the session, never from the request body.
 */
class AttendanceScanController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $sessions) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorize('clock', AttendanceRecord::class);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'action' => ['required', Rule::in(AttendanceSession::ACTIONS)],
        ]);

        try {
            $result = $this->sessions->scan($request->user(), $validated['token'], $validated['action'], $request);
        } catch (AttendanceScanException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->reason,
            ], $e->statusCode);
        }

        return response()->json([
            'message' => $validated['action'] === AttendanceSession::ACTION_CLOCK_IN
                ? 'Clocked in successfully.'
                : 'Clocked out successfully.',
            'data' => $result['record']->load('shiftTemplate:id,name'),
        ]);
    }
}
