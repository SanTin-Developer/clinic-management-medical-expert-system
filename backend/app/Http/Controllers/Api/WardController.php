<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ward::class);

        $query = Ward::query()->withCount(['rooms', 'activeAdmissions']);

        if ($type = $request->query('ward_type')) {
            $query->where('ward_type', $type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $wards = $query->orderBy('name')->paginate(15);

        $stats = [
            'total' => Ward::query()->count(),
            'active' => Ward::query()->where('is_active', true)->count(),
            'inactive' => Ward::query()->where('is_active', false)->count(),
            'admitted' => Admission::query()->where('status', Admission::STATUS_ADMITTED)->count(),
        ];

        return response()->json(array_merge($wards->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ward::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'unique:wards,code'],
            'floor' => ['nullable', 'string', 'max:30'],
            'ward_type' => ['nullable', Rule::in($this->wardTypes())],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $ward = Ward::create($validated);

        return response()->json([
            'message' => 'Ward created successfully.',
            'data' => $ward,
        ], 201);
    }

    public function show(Ward $ward): JsonResponse
    {
        $this->authorize('view', $ward);

        $ward->load([
            'rooms' => fn ($query) => $query->withCount('beds'),
            'activeAdmissions.patient',
        ]);

        return response()->json(['data' => $ward]);
    }

    public function update(Request $request, Ward $ward): JsonResponse
    {
        $this->authorize('update', $ward);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('wards', 'code')->ignore($ward->id)],
            'floor' => ['sometimes', 'nullable', 'string', 'max:30'],
            'ward_type' => ['sometimes', Rule::in($this->wardTypes())],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $ward->update($validated);

        return response()->json([
            'message' => 'Ward updated successfully.',
            'data' => $ward->fresh(),
        ]);
    }

    public function destroy(Ward $ward): JsonResponse
    {
        $this->authorize('delete', $ward);

        if ($ward->activeAdmissions()->exists()) {
            return response()->json([
                'message' => 'A ward with active admissions cannot be deleted.',
            ], 422);
        }

        $ward->delete();

        return response()->json(['message' => 'Ward deleted successfully.']);
    }

    /**
     * @return array<int, string>
     */
    private function wardTypes(): array
    {
        return [
            Ward::TYPE_GENERAL,
            Ward::TYPE_ICU,
            Ward::TYPE_MATERNITY,
            Ward::TYPE_PEDIATRIC,
            Ward::TYPE_SURGICAL,
            Ward::TYPE_ISOLATION,
        ];
    }
}
