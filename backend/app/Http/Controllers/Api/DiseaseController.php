<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiseaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Disease::class);

        $query = Disease::with('symptoms:id,code,name')->withCount('symptoms');
        $base = clone $query;

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('name_km', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $diseases = $query->latest()->paginate(15);

        $stats = [
            'total' => $diseases->total(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'by_category' => (clone $base)->selectRaw('category, COUNT(*) as count')->groupBy('category')->pluck('count', 'category'),
        ];

        return response()->json(array_merge($diseases->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Disease::class);

        $validated = $request->validate($this->rules());
        $validated['code'] = $validated['code'] ?? $this->generateCode();

        $symptomIds = $validated['symptom_ids'] ?? null;
        unset($validated['symptom_ids']);

        $disease = DB::transaction(function () use ($validated, $symptomIds) {
            $disease = Disease::create($validated);

            if ($symptomIds !== null) {
                $disease->symptoms()->sync($symptomIds);
            }

            return $disease;
        });

        return response()->json([
            'message' => 'Disease created successfully.',
            'data' => $disease->load('symptoms:id,code,name'),
        ], 201);
    }

    public function show(Disease $disease): JsonResponse
    {
        $this->authorize('view', $disease);

        return response()->json([
            'data' => $disease->load([
                'symptoms:id,code,name',
                'expertRules:id,code,name,disease_id',
            ]),
        ]);
    }

    public function update(Request $request, Disease $disease): JsonResponse
    {
        $this->authorize('update', $disease);

        $validated = $request->validate($this->rules($disease));

        if (($validated['code'] ?? null) === null) {
            unset($validated['code']);
        }

        $symptomIds = $validated['symptom_ids'] ?? null;
        unset($validated['symptom_ids']);

        DB::transaction(function () use ($disease, $validated, $symptomIds) {
            $disease->update($validated);

            if ($symptomIds !== null) {
                $disease->symptoms()->sync($symptomIds);
            }
        });

        return response()->json([
            'message' => 'Disease updated successfully.',
            'data' => $disease->fresh()->load('symptoms:id,code,name'),
        ]);
    }

    public function destroy(Disease $disease): JsonResponse
    {
        $this->authorize('delete', $disease);

        if ($disease->expertRules()->exists()) {
            return response()->json([
                'message' => 'This disease still has expert rules and cannot be deleted.',
            ], 422);
        }

        $disease->delete();

        return response()->json([
            'message' => 'Disease deleted successfully.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Disease $disease = null): array
    {
        $required = $disease ? 'sometimes' : 'required';

        return [
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('diseases', 'code')->ignore($disease?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'name_km' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity_level' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'symptom_ids' => ['sometimes', 'array'],
            'symptom_ids.*' => ['integer', 'exists:symptoms,id'],
        ];
    }

    private function generateCode(): string
    {
        do {
            $code = 'DIS-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Disease::where('code', $code)->exists());

        return $code;
    }
}
