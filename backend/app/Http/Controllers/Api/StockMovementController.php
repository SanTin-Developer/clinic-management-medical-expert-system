<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Pharmacy\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\Pharmacy\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = StockMovement::with(['medicine', 'inventoryItem', 'user']);

        if ($medicineId = $request->query('medicine_id')) {
            $query->where('medicine_id', $medicineId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($inventoryItemId = $request->query('inventory_item_id')) {
            $query->where('inventory_item_id', $inventoryItemId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'ilike', "%{$search}%")
                    ->orWhere('reason', 'ilike', "%{$search}%")
                    ->orWhereHas('medicine', function ($medicineQuery) use ($search) {
                        $medicineQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                    })->orWhereHas('inventoryItem', function ($itemQuery) use ($search) {
                        $itemQuery->where('batch_number', 'ilike', "%{$search}%");
                    });
            });
        }

        $statsQuery = clone $query;
        $movements = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json(array_merge($movements->toArray(), [
            'stats' => [
                'total' => $movements->total(),
                'in' => (clone $statsQuery)->where('type', 'in')->count(),
                'out' => (clone $statsQuery)->where('type', 'out')->count(),
                'adjustment' => (clone $statsQuery)->where('type', 'adjustment')->count(),
            ],
        ]));
    }

    public function store(Request $request, InventoryService $inventory): JsonResponse
    {
        $this->authorize('create', StockMovement::class);

        $validated = $request->validate([
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);

        if ((int) $item->medicine_id !== (int) $validated['medicine_id']) {
            return response()->json([
                'message' => 'The inventory item does not belong to the selected medicine.',
            ], 422);
        }

        try {
            $movement = DB::transaction(fn () => $inventory->applyMovement(
                item: $item,
                type: $validated['type'],
                quantity: $validated['quantity'],
                userId: $request->user()->id,
                reason: $validated['reason'] ?? null,
                reference: $validated['reference'] ?? null,
            ));
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Stock movement recorded successfully.',
            'data' => $movement->load(['medicine', 'inventoryItem', 'user']),
        ], 201);
    }
}
