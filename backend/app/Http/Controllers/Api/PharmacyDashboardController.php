<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyDashboardController extends Controller
{
    /**
     * Aggregate the key pharmacy indicators (stock health, expiry risk and
     * dispensing activity) from the existing catalog/inventory/billing tables.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('inventory.view') || $request->user()->can('medicines.view'),
            403,
            'This action is unauthorized.'
        );

        $today = now()->toDateString();
        $soon = now()->addDays(30)->toDateString();

        $medicines = Medicine::query()
            ->where('is_active', true)
            ->withSum('inventoryItems as total_stock', 'quantity')
            ->get(['id', 'name', 'code', 'price', 'reorder_level']);

        $lowStock = $medicines
            ->filter(fn (Medicine $medicine) => (int) $medicine->total_stock <= (int) $medicine->reorder_level)
            ->values();

        $outOfStock = $medicines
            ->filter(fn (Medicine $medicine) => (int) $medicine->total_stock <= 0)
            ->values();

        $expiringSoon = InventoryItem::with('medicine')
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $soon)
            ->orderBy('expiration_date')
            ->get();

        $expiredCount = InventoryItem::query()
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', $today)
            ->count();

        $stockValue = $medicines->sum(
            fn (Medicine $medicine) => (int) $medicine->total_stock * (float) $medicine->price
        );

        $movementsToday = StockMovement::query()
            ->whereDate('created_at', $today)
            ->selectRaw('type, sum(quantity) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return response()->json([
            'data' => [
                'inventory' => [
                    'active_medicines' => $medicines->count(),
                    'total_on_hand' => (int) InventoryItem::where('quantity', '>', 0)->sum('quantity'),
                    'stock_value' => round((float) $stockValue, 2),
                    'low_stock_count' => $lowStock->count(),
                    'out_of_stock_count' => $outOfStock->count(),
                    'expiring_soon_count' => $expiringSoon->count(),
                    'expired_count' => $expiredCount,
                ],
                'prescriptions' => [
                    'pending' => Prescription::where('status', 'pending')->count(),
                    'dispensed_today' => Prescription::where('status', 'dispensed')
                        ->whereDate('dispensed_at', $today)
                        ->count(),
                ],
                'movements_today' => [
                    'in' => (int) ($movementsToday['in'] ?? 0),
                    'out' => (int) ($movementsToday['out'] ?? 0),
                    'adjustment' => (int) ($movementsToday['adjustment'] ?? 0),
                ],
                'low_stock' => $lowStock->take(10)->map(fn (Medicine $medicine) => [
                    'id' => $medicine->id,
                    'name' => $medicine->name,
                    'code' => $medicine->code,
                    'stock' => (int) $medicine->total_stock,
                    'reorder_level' => (int) $medicine->reorder_level,
                ])->values(),
                'expiring_soon' => $expiringSoon->take(10)->map(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'medicine' => $item->medicine?->name,
                    'batch_number' => $item->batch_number,
                    'quantity' => (int) $item->quantity,
                    'expiration_date' => $item->expiration_date?->toDateString(),
                ])->values(),
                'recent_movements' => StockMovement::with(['medicine:id,name', 'user:id,name'])
                    ->latest()
                    ->limit(10)
                    ->get(),
                'recent_dispensed' => Prescription::with(['patient', 'dispensedBy'])
                    ->where('status', 'dispensed')
                    ->orderByDesc('dispensed_at')
                    ->limit(10)
                    ->get(),
            ],
        ]);
    }
}
