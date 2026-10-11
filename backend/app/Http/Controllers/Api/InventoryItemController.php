<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $query = InventoryItem::with('medicine');

        if ($medicineId = $request->query('medicine_id')) {
            $query->where('medicine_id', $medicineId);
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw(
                'quantity <= (select reorder_level from medicines where medicines.id = inventory_items.medicine_id)'
            );
        }

        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now()->addDays(30))
                ->whereDate('expiration_date', '>=', now());
        }

        return response()->json(
            $query->orderBy('expiration_date')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', InventoryItem::class);

        $validated = $request->validate([
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $item = InventoryItem::create($validated);

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
            'batch_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'expiration_date' => ['sometimes', 'nullable', 'date'],
            'received_date' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $inventoryItem->update($validated);

        return response()->json([
            'message' => 'Inventory item updated successfully.',
            'data' => $inventoryItem->fresh()->load('medicine'),
        ]);
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('delete', $inventoryItem);

        $inventoryItem->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }
}
