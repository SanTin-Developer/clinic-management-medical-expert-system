<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillableServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BillableService::class);

        $query = BillableService::query();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $services = $query->orderBy('category')->orderBy('name')->paginate(50);

        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BillableService::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:billable_services,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['lab', 'imaging', 'procedure', 'nursing', 'consultation', 'supply', 'room', 'other'])],
            'unit' => ['nullable', 'string', 'max:30'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'requires_approval' => ['boolean'],
            'price_source' => ['nullable', 'string', Rule::in(['catalog', 'fixed'])],
        ]);

        $service = BillableService::create($validated);

        return response()->json(['message' => 'Billable service created.', 'data' => $service], 201);
    }

    public function show(BillableService $billableService): JsonResponse
    {
        $this->authorize('view', $billableService);

        return response()->json(['data' => $billableService]);
    }

    public function update(Request $request, BillableService $billableService): JsonResponse
    {
        $this->authorize('update', $billableService);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('billable_services', 'code')->ignore($billableService->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', Rule::in(['lab', 'imaging', 'procedure', 'nursing', 'consultation', 'supply', 'room', 'other'])],
            'unit' => ['nullable', 'string', 'max:30'],
            'default_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'requires_approval' => ['boolean'],
            'price_source' => ['nullable', 'string', Rule::in(['catalog', 'fixed'])],
        ]);

        $billableService->update($validated);

        return response()->json(['message' => 'Billable service updated.', 'data' => $billableService->fresh()]);
    }

    public function destroy(BillableService $billableService): JsonResponse
    {
        $this->authorize('delete', $billableService);

        $billableService->delete();

        return response()->json(['message' => 'Billable service deleted.']);
    }
}
