<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpertRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpertRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpertRule::class);

        $query = ExpertRule::with([
            'disease:id,code,name',
            'conditions.symptom:id,code,name',
        ]);

        if ($diseaseId = $request->query('disease_id')) {
            $query->where('disease_id', $diseaseId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExpertRule::class);

        $validated = $request->validate($this->rules());

        $this->assertNoDuplicateConditions($validated['conditions'] ?? []);

        $conditions = $validated['conditions'] ?? [];
        unset($validated['conditions']);

        $rule = DB::transaction(function () use ($validated, $conditions) {
            $rule = ExpertRule::create($validated);

            if ($conditions !== []) {
                $rule->conditions()->createMany($conditions);
            }

            return $rule;
        });

        return response()->json([
            'message' => 'Expert rule created successfully.',
            'data' => $rule->load(['disease:id,code,name', 'conditions.symptom:id,code,name']),
        ], 201);
    }

    public function show(ExpertRule $expertRule): JsonResponse
    {
        $this->authorize('view', $expertRule);

        return response()->json([
            'data' => $expertRule->load([
                'disease:id,code,name',
                'conditions.symptom:id,code,name',
            ]),
        ]);
    }

    public function update(Request $request, ExpertRule $expertRule): JsonResponse
    {
        $this->authorize('update', $expertRule);

        $validated = $request->validate($this->rules($expertRule));

        if (array_key_exists('conditions', $validated)) {
            $this->assertNoDuplicateConditions($validated['conditions']);
        }

        $conditions = $validated['conditions'] ?? null;
        unset($validated['conditions']);

        DB::transaction(function () use ($expertRule, $validated, $conditions) {
            $expertRule->update($validated);

            if ($conditions !== null) {
                $expertRule->conditions()->delete();

                if ($conditions !== []) {
                    $expertRule->conditions()->createMany($conditions);
                }
            }
        });

        return response()->json([
            'message' => 'Expert rule updated successfully.',
            'data' => $expertRule->fresh()->load([
                'disease:id,code,name',
                'conditions.symptom:id,code,name',
            ]),
        ]);
    }

    public function destroy(ExpertRule $expertRule): JsonResponse
    {
        $this->authorize('delete', $expertRule);

        $expertRule->delete();

        return response()->json([
            'message' => 'Expert rule deleted successfully.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    private function assertNoDuplicateConditions(array $conditions): void
    {
        $seen = [];

        foreach ($conditions as $condition) {
            $key = ($condition['symptom_id'] ?? '').':'.($condition['condition_group'] ?? 1);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'conditions' => [
                        'The same symptom cannot appear twice within the same condition group.',
                    ],
                ]);
            }

            $seen[$key] = true;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?ExpertRule $rule = null): array
    {
        $required = $rule ? 'sometimes' : 'required';

        return [
            'code' => [
                $required,
                'string',
                'max:255',
                Rule::unique('expert_rules', 'code')->ignore($rule?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'disease_id' => [$required, 'integer', 'exists:diseases,id'],
            'explanation' => ['nullable', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'confidence_score' => ['sometimes', 'numeric', 'between:0,1'],
            'is_active' => ['sometimes', 'boolean'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.symptom_id' => ['required', 'integer', 'exists:symptoms,id'],
            'conditions.*.operator' => ['sometimes', Rule::in(['present', 'absent'])],
            'conditions.*.expected_value' => ['sometimes', 'boolean'],
            'conditions.*.condition_group' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
