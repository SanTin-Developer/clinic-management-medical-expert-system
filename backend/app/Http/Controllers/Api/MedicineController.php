<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Medicine::class);

        $query = Medicine::query();

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Medicine::class);

        $validated = $request->validate($this->rules());

        $validated['code'] = $validated['code'] ?? $this->generateCode();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $medicine = Medicine::create($validated);

        return response()->json([
            'message' => 'Medicine created successfully.',
            'data' => $medicine,
        ], 201);
    }

    public function show(Medicine $medicine): JsonResponse
    {
        $this->authorize('view', $medicine);

        return response()->json([
            'data' => $medicine->load('inventoryItems'),
        ]);
    }

    public function update(Request $request, Medicine $medicine): JsonResponse
    {
        $this->authorize('update', $medicine);

        $validated = $request->validate($this->rules($medicine));

        $medicine->update($validated);

        return response()->json([
            'message' => 'Medicine updated successfully.',
            'data' => $medicine->fresh(),
        ]);
    }

    public function destroy(Medicine $medicine): JsonResponse
    {
        $this->authorize('delete', $medicine);

        $medicine->delete();

        return response()->json([
            'message' => 'Medicine deleted successfully.',
        ]);
    }

    /**
     * Validation rules shared by store and update.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Medicine $medicine = null): array
    {
        $required = $medicine ? 'sometimes' : 'required';

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('medicines', 'code')->ignore($medicine?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'string', 'max:50'],
            'strength' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function generateCode(): string
    {
        do {
            $code = 'MED-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Medicine::where('code', $code)->exists());

        return $code;
    }
}
