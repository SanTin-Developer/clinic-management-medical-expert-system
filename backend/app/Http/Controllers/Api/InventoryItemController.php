<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\StockMovement;
use App\Services\Pharmacy\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $query = InventoryItem::with('medicine');
        $base = clone $query;

        if ($medicineId = $request->query('medicine_id')) {
            $query->where('medicine_id', $medicineId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('medicine', function ($medicineQuery) use ($search) {
                    $medicineQuery->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                })->orWhere('batch_number', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock')) {
            $query->whereIn('medicine_id', $this->lowStockMedicineIds());
        }

        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now()->addDays(30))
                ->whereDate('expiration_date', '>=', now());
        }

        if ($request->boolean('expired')) {
            $query->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', now());
        }

        $items = $query->orderBy('expiration_date')->paginate($request->integer('per_page', 15));

        $stats = [
            'total' => $items->total(),
            'low_stock' => (clone $base)->whereIn('medicine_id', $this->lowStockMedicineIds())->count(),
            'expiring_soon' => (clone $base)->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now()->addDays(30))
                ->whereDate('expiration_date', '>=', now())
                ->count(),
            'expired' => (clone $base)->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', now())
                ->count(),
            'out_of_stock' => (clone $base)->where('quantity', '<=', 0)->count(),
        ];

        return response()->json(array_merge($items->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request, InventoryService $inventory): JsonResponse
    {
        $this->authorize('create', InventoryItem::class);

        $validated = $request->validate([
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'batch_number' => [
                'required', 'string', 'max:100',
                Rule::unique('inventory_items')->where(fn ($query) => $query->where('medicine_id', $request->integer('medicine_id'))),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiration_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $item = DB::transaction(function () use ($validated, $inventory, $request) {
            $item = InventoryItem::create(collect($validated)->except('reference')->all());
            $inventory->recordReceipt($item, $request->user()->id, $request->input('reference'));

            return $item;
        });

        return response()->json([
            'message' => 'Inventory item created successfully.',
            'data' => $item->load('medicine'),
        ], 201);
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('view', $inventoryItem);

        return response()->json([
            'data' => $inventoryItem->load(['medicine', 'stockMovements']),
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('update', $inventoryItem);

        $validated = $request->validate([
            'batch_number' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('inventory_items')->where(fn ($query) => $query->where('medicine_id', $inventoryItem->medicine_id))->ignore($inventoryItem->id),
            ],
            'expiration_date' => ['sometimes', 'nullable', 'date'],
            'received_date' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if ($request->has('quantity') && (int) $request->input('quantity') !== (int) $inventoryItem->quantity) {
            return response()->json([
                'message' => 'Change stock quantity using an auditable stock movement.',
            ], 422);
        }

        $inventoryItem->update($validated);

        return response()->json([
            'message' => 'Inventory item updated successfully.',
            'data' => $inventoryItem->fresh()->load('medicine'),
        ]);
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('delete', $inventoryItem);

        if (StockMovement::where('inventory_item_id', $inventoryItem->id)->exists()) {
            return response()->json([
                'message' => 'A batch with stock movements cannot be deleted. Record an adjustment instead.',
            ], 422);
        }

        $inventoryItem->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }

    private function lowStockMedicineIds(): Builder
    {
        return Medicine::query()
            ->select('id')
            ->whereRaw('(select coalesce(sum(quantity), 0) from inventory_items as stock_batches where stock_batches.medicine_id = medicines.id) <= medicines.reorder_level');
    }
}
