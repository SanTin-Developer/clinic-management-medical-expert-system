<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\Attendance\ShiftSchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftSchedulingService $scheduling) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $user = $request->user();
        $canManage = $user->can('scheduling.manage');

        $query = Shift::query()->with('user:id,name', 'shiftTemplate:id,name,type');

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

        if ($templateId = $request->query('shift_template_id')) {
            $query->where('shift_template_id', $templateId);
        }

        if ($request->boolean('unpublished')) {
            $query->whereNull('published_at');
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
            'unpublished' => (clone $base)->whereNull('published_at')->count(),
        ];

        return response()->json(array_merge($shifts->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_date' => ['required', 'date'],
            'shift_template_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_templates,id'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'type' => ['sometimes', Rule::in(Shift::TYPES)],
            'status' => ['sometimes', Rule::in(Shift::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolve = $this->resolveTimes($validated);

        $user = User::findOrFail($validated['user_id']);
        $candidate = $this->scheduling->candidateInterval(
            $validated['shift_date'], $resolve['start_time'], $resolve['end_time'], $resolve['crosses_midnight'],
        );
        $this->scheduling->assertNoConflict($user, $validated['shift_date'], $candidate);

        $shift = Shift::create(array_merge([
            'user_id' => $validated['user_id'],
            'shift_date' => $validated['shift_date'],
            'type' => $resolve['type'],
            'status' => $validated['status'] ?? 'scheduled',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ], $resolve['attributes']));

        return response()->json([
            'message' => 'Shift scheduled.',
            'data' => $shift->load('user:id,name', 'shiftTemplate:id,name,type'),
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);

        return response()->json([
            'data' => $shift->load('user:id,name', 'user.staff:user_id,staff_number,department', 'shiftTemplate:id,name,type'),
        ]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'shift_date' => ['sometimes', 'date'],
            'shift_template_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_templates,id'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'type' => ['sometimes', Rule::in(Shift::TYPES)],
            'status' => ['sometimes', Rule::in(Shift::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $templateId = array_key_exists('shift_template_id', $validated)
            ? $validated['shift_template_id']
            : $shift->shift_template_id;

        $template = $templateId ? ShiftTemplate::find($templateId) : null;

        $resolve = $this->resolveTimes(array_merge([
            'start_time' => substr((string) $shift->start_time, 0, 5),
            'end_time' => substr((string) $shift->end_time, 0, 5),
            'crosses_midnight' => $shift->crosses_midnight,
            'type' => $shift->type,
            'shift_template_id' => $templateId,
        ], $validated), $template);

        $date = $validated['shift_date'] ?? $shift->shift_date->toDateString();
        $userId = $validated['user_id'] ?? $shift->user_id;

        $candidate = $this->scheduling->candidateInterval(
            $date, $resolve['start_time'], $resolve['end_time'], $resolve['crosses_midnight'],
        );
        $this->scheduling->assertNoConflict(User::findOrFail($userId), $date, $candidate, $shift->id);

        $payload = array_merge([
            'user_id' => $userId,
            'shift_date' => $date,
            'type' => $resolve['type'],
        ], $resolve['attributes']);

        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }

        $shift->update($payload);

        return response()->json([
            'message' => 'Shift updated.',
            'data' => $shift->fresh('user:id,name', 'shiftTemplate:id,name,type'),
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);

        $shift->delete();

        return response()->json(['message' => 'Shift removed.']);
    }

    public function board(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $from = $request->query('from', now()->startOfWeek()->toDateString());
        $to = $request->query('to', now()->endOfWeek()->toDateString());

        $query = Shift::query()
            ->with('user:id,name', 'user.staff:user_id,staff_number,department', 'shiftTemplate:id,name,type,start_time,end_time')
            ->whereDate('shift_date', '>=', $from)
            ->whereDate('shift_date', '<=', $to);

        if (! $request->user()->can('scheduling.manage')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($department = $request->query('department')) {
            $query->whereHas('user.staff', fn ($builder) => $builder->where('department', $department));
        }

        if ($templateId = $request->query('shift_template_id')) {
            $query->where('shift_template_id', $templateId);
        }

        $shifts = $query->orderBy('shift_date')->orderBy('start_time')->get();

        return response()->json(['data' => $shifts, 'from' => $from, 'to' => $to]);
    }

    public function uncovered(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'shift_template_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_templates,id'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        if ($from->diffInDays($to) > 62) {
            throw ValidationException::withMessages(['to' => 'The date range may not exceed 62 days.']);
        }

        $templates = ! empty($validated['shift_template_id'])
            ? ShiftTemplate::whereKey($validated['shift_template_id'])->get()
            : ShiftTemplate::where('is_active', true)->orderBy('sort_order')->get();

        $rows = [];

        foreach ($templates as $template) {
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $assigned = Shift::query()
                    ->where('shift_template_id', $template->id)
                    ->whereDate('shift_date', $day->toDateString())
                    ->where('status', '!=', 'cancelled')
                    ->count();

                if ($assigned === 0) {
                    $rows[] = [
                        'shift_template_id' => $template->id,
                        'name' => $template->name,
                        'type' => $template->type,
                        'date' => $day->toDateString(),
                    ];
                }
            }
        }

        return response()->json(['data' => $rows]);
    }

    public function publish(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $shift->update(['published_at' => now()]);

        return response()->json([
            'message' => 'Shift published.',
            'data' => $shift->fresh('user:id,name'),
        ]);
    }

    public function publishBulk(Request $request): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $validated = $request->validate([
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer', 'exists:shifts,id'],
            'from' => ['required_without:ids', 'date'],
            'to' => ['required_without:ids', 'date', 'after_or_equal:from'],
            'shift_template_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_templates,id'],
        ]);

        $query = Shift::query()->whereNull('published_at');

        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        } else {
            $query->whereDate('shift_date', '>=', $validated['from'])
                ->whereDate('shift_date', '<=', $validated['to']);

            if (! empty($validated['shift_template_id'])) {
                $query->where('shift_template_id', $validated['shift_template_id']);
            }
        }

        $count = $query->update(['published_at' => now()]);

        return response()->json([
            'message' => "{$count} shift(s) published.",
            'data' => ['published' => $count],
        ]);
    }

    /**
     * Resolve the effective start/end/template for a create or update payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array{start_time: string, end_time: string, crosses_midnight: bool, type: string, attributes: array<string, mixed>}
     */
    private function resolveTimes(array $validated, ?ShiftTemplate $template = null): array
    {
        if (! $template && ! empty($validated['shift_template_id'])) {
            $template = ShiftTemplate::find($validated['shift_template_id']);
        }

        if ($template) {
            return [
                'start_time' => substr((string) $template->start_time, 0, 5),
                'end_time' => substr((string) $template->end_time, 0, 5),
                'crosses_midnight' => (bool) $template->crosses_midnight,
                'type' => $template->type,
                'attributes' => [
                    'shift_template_id' => $template->id,
                    'start_time' => substr((string) $template->start_time, 0, 5),
                    'end_time' => substr((string) $template->end_time, 0, 5),
                    'crosses_midnight' => (bool) $template->crosses_midnight,
                ],
            ];
        }

        $start = $validated['start_time'] ?? null;
        $end = $validated['end_time'] ?? null;

        if (! $start || ! $end) {
            throw ValidationException::withMessages([
                'start_time' => 'A start time and end time, or a shift template, is required.',
            ]);
        }

        $crosses = (bool) ($validated['crosses_midnight'] ?? false);

        if (! $crosses && strcmp($end, $start) <= 0) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be after the start time.',
            ]);
        }

        return [
            'start_time' => $start,
            'end_time' => $end,
            'crosses_midnight' => $crosses,
            'type' => $validated['type'] ?? 'day',
            'attributes' => [
                'shift_template_id' => null,
                'start_time' => $start,
                'end_time' => $end,
                'crosses_midnight' => $crosses,
            ],
        ];
    }
}
