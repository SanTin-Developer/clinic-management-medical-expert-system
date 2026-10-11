<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
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

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
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

        if ($validated['type'] === 'out' && $item->quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient stock for this stock-out movement.',
            ], 422);
        }

        $movement = DB::transaction(function () use ($validated, $item, $request) {
            match ($validated['type']) {
                'in' => $item->quantity += $validated['quantity'],
                'out' => $item->quantity -= $validated['quantity'],
                'adjustment' => $item->quantity = $validated['quantity'],
            };

            $item->save();

            return StockMovement::create([
                'medicine_id' => $validated['medicine_id'],
                'inventory_item_id' => $item->id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'] ?? null,
                'reference' => $validated['reference'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Stock movement recorded successfully.',
            'data' => $movement->load(['medicine', 'inventoryItem', 'user']),
        ], 201);
    }
}
