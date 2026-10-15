<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\ShiftTemplate;
use App\Services\Attendance\AttendanceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rotating QR attendance sessions. Administrators open a session for a shift
 * template/date/action, the display refreshes a 5-second token, and staff scan
 * it from their own authenticated account.
 */
class AttendanceSessionController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $query = AttendanceSession::query()->with('shiftTemplate:id,name,type,start_time,end_time');

        if ($date = $request->query('date')) {
            $query->whereDate('session_date', $date);
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($templateId = $request->query('shift_template_id')) {
            $query->where('shift_template_id', $templateId);
        }

        return response()->json($query->orderByDesc('session_date')->orderByDesc('id')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AttendanceSession::class);

        $validated = $request->validate([
            'shift_template_id' => ['required', 'integer', 'exists:shift_templates,id'],
            'session_date' => ['required', 'date'],
            'action' => ['required', Rule::in(AttendanceSession::ACTIONS)],
        ]);

        $session = $this->sessions->open(
            $request->user(),
            ShiftTemplate::findOrFail($validated['shift_template_id']),
            $validated['session_date'],
            $validated['action'],
            $request,
        );

        return response()->json([
            'message' => 'Attendance session opened.',
            'data' => $this->sessions->payload($session),
        ], 201);
    }

    public function show(AttendanceSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json(['data' => $this->sessions->payload($session)]);
    }

    public function token(AttendanceSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json(['data' => $this->sessions->currentToken($session)]);
    }

    public function close(Request $request, AttendanceSession $session): JsonResponse
    {
        $this->authorize('close', $session);

        $closed = $this->sessions->close($request->user(), $session, 'manual', $request);

        return response()->json([
            'message' => 'Attendance session closed.',
            'data' => $this->sessions->payload($closed),
        ]);
    }
}
