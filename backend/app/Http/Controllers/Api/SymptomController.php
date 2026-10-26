<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Symptom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SymptomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Symptom::class);

        $query = Symptom::with('diseases:id,code,name,name_km')->withCount('diseases');
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

        $symptoms = $query->latest()->paginate(15);

        $stats = [
            'total' => $symptoms->total(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
        ];

        return response()->json(array_merge($symptoms->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Symptom::class);

        $validated = $request->validate($this->rules());
        $validated['code'] = $validated['code'] ?? $this->generateCode();

        $diseaseIds = $validated['disease_ids'] ?? null;
        unset($validated['disease_ids']);

        $symptom = DB::transaction(function () use ($validated, $diseaseIds) {
            $symptom = Symptom::create($validated);

            if ($diseaseIds !== null) {
                $symptom->diseases()->sync($diseaseIds);
            }

            return $symptom;
        });

        return response()->json([
            'message' => 'Symptom created successfully.',
            'data' => $symptom->load('diseases:id,code,name,name_km'),
        ], 201);
    }

    public function show(Symptom $symptom): JsonResponse
    {
        $this->authorize('view', $symptom);

        return response()->json([
            'data' => $symptom->load('diseases:id,code,name,name_km'),
        ]);
    }

    public function update(Request $request, Symptom $symptom): JsonResponse
    {
        $this->authorize('update', $symptom);

        $validated = $request->validate($this->rules($symptom));

        if (($validated['code'] ?? null) === null) {
            unset($validated['code']);
        }

        $diseaseIds = $validated['disease_ids'] ?? null;
        unset($validated['disease_ids']);

        DB::transaction(function () use ($symptom, $validated, $diseaseIds) {
            $symptom->update($validated);

            if ($diseaseIds !== null) {
                $symptom->diseases()->sync($diseaseIds);
            }
        });

        return response()->json([
            'message' => 'Symptom updated successfully.',
            'data' => $symptom->fresh()->load('diseases:id,code,name,name_km'),
        ]);
    }

    public function destroy(Symptom $symptom): JsonResponse
    {
        $this->authorize('delete', $symptom);

        if ($symptom->expertRuleConditions()->exists()) {
            return response()->json([
                'message' => 'This symptom is referenced by expert rules and cannot be deleted.',
            ], 422);
        }

        $symptom->delete();

        return response()->json([
            'message' => 'Symptom deleted successfully.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Symptom $symptom = null): array
    {
        $required = $symptom ? 'sometimes' : 'required';

        return [
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('symptoms', 'code')->ignore($symptom?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'name_km' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'disease_ids' => ['sometimes', 'array'],
            'disease_ids.*' => ['integer', 'exists:diseases,id'],
        ];
    }

    private function generateCode(): string
    {
        do {
            $code = 'SYM-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Symptom::where('code', $code)->exists());

        return $code;
    }
}
