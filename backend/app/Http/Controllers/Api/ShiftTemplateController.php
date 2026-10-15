<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftTemplate::class);

        $query = ShiftTemplate::query()->orderBy('sort_order')->orderBy('name');

        if (! ($request->user()->can('scheduling.manage') && $request->boolean('all'))) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ShiftTemplate::class);

        $validated = $request->validate($this->rules());

        $template = ShiftTemplate::create($validated);

        return response()->json([
            'message' => 'Shift template created.',
            'data' => $template,
        ], 201);
    }

    public function show(ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('view', $shiftTemplate);

        return response()->json(['data' => $shiftTemplate]);
    }

    public function update(Request $request, ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('update', $shiftTemplate);

        $validated = $request->validate($this->rules($shiftTemplate));

        $shiftTemplate->update($validated);

        return response()->json([
            'message' => 'Shift template updated.',
            'data' => $shiftTemplate->fresh(),
        ]);
    }

    public function destroy(ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('delete', $shiftTemplate);

        $shiftTemplate->delete();

        return response()->json(['message' => 'Shift template deleted.']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?ShiftTemplate $template = null): array
    {
        $required = $template ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:80', Rule::unique('shift_templates', 'name')->ignore($template?->id)],
            'type' => [$required, Rule::in(ShiftTemplate::TYPES)],
            'start_time' => [$required, 'date_format:H:i'],
            'clock_in_cutoff' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'clock_out_opens_at' => ['nullable', 'date_format:H:i'],
            'clock_out_closes_at' => ['nullable', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }
}
