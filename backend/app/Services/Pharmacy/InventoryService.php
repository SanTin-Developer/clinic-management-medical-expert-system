<?php

namespace App\Services\Pharmacy;

use App\Exceptions\Pharmacy\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * Single source of truth for stock mutations.
 *
 * Every method that changes inventory MUST be called inside a database
 * transaction: row-level locks acquired via lockForUpdate() only hold for the
 * duration of the surrounding transaction.
 */
class InventoryService
{
    /**
     * Record the initial receipt for a newly-created batch. The batch itself is
     * created by the receiving workflow; this method keeps its opening balance
     * visible in the same immutable movement ledger as all later changes.
     */
    public function recordReceipt(InventoryItem $item, int $userId, ?string $reference = null): StockMovement
    {
        return $this->recordMovement(
            item: $item,
            type: 'in',
            quantity: (int) $item->quantity,
            userId: $userId,
            reason: 'Stock receipt',
            reference: $reference,
        );
    }

    /**
     * Deduct a set of prescribed items using FEFO (first-expiry, first-out),
     * ignoring expired batches and atomically decrementing each batch touched.
     *
     * @param  iterable<int, object{medicine: Medicine, quantity: int}>  $items
     * @return Collection<int, StockMovement>
     */
    public function allocateForItems(iterable $items, int $userId, string $reason, ?string $reference = null): Collection
    {
        $movements = collect();

        foreach ($items as $item) {
            $batches = $this->allocate(
                medicine: $item->medicine,
                quantity: (int) $item->quantity,
                userId: $userId,
                type: 'out',
                reason: $reason,
                reference: $reference,
            );

            foreach ($batches as $result) {
                $movements->push($result['movement']);
            }
        }

        return $movements;
    }

    /**
     * Allocate a quantity of a single medicine across usable batches.
     *
     * @return array<int, array{batch: InventoryItem, quantity: int, movement: StockMovement}>
     */
    public function allocate(
        Medicine $medicine,
        int $quantity,
        int $userId,
        string $type = 'out',
        ?string $reason = null,
        ?string $reference = null,
    ): array {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Allocated quantity must be at least 1.');
        }

        $base = InventoryItem::query()
            ->where('medicine_id', $medicine->id)
            ->where('quantity', '>', 0);

        $totalOnHand = (clone $base)->sum('quantity');

        $usableBatches = (clone $base)
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', now()->toDateString());
            })
            ->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
            ->lockForUpdate()
            ->get();

        $usable = (int) $usableBatches->sum('quantity');

        if ($usable < $quantity) {
            $message = $usable === 0 && $totalOnHand > 0
                ? "Only expired stock is available for {$medicine->name}."
                : "Insufficient stock for {$medicine->name}.";

            throw new InsufficientStockException($message, $medicine->name, $quantity, $usable);
        }

        $remaining = $quantity;
        $allocations = [];

        foreach ($usableBatches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $batch->quantity, $remaining);

            $batch->quantity -= $take;
            $batch->save();

            $remaining -= $take;

            $allocations[] = [
                'batch' => $batch,
                'quantity' => $take,
                'movement' => $this->recordMovement(
                    item: $batch,
                    type: $type,
                    quantity: $take,
                    userId: $userId,
                    reason: $reason,
                    reference: $reference,
                ),
            ];
        }

        return $allocations;
    }

    /**
     * Apply a manual stock movement to a single batch, guarding stock-out
     * against the current locked balance. Must run inside a transaction.
     */
    public function applyMovement(
        InventoryItem $item,
        string $type,
        int $quantity,
        int $userId,
        ?string $reason = null,
        ?string $reference = null,
    ): StockMovement {
        if (! in_array($type, ['in', 'out', 'adjustment'], true)) {
            throw new \InvalidArgumentException("Unsupported stock movement type [{$type}].");
        }

        $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

        if ($type === 'out' && (int) $locked->quantity < $quantity) {
            throw new InsufficientStockException(
                'Insufficient stock for this stock-out movement.',
                $locked->medicine?->name,
                $quantity,
                (int) $locked->quantity,
            );
        }

        match ($type) {
            'in' => $locked->quantity += $quantity,
            'out' => $locked->quantity -= $quantity,
            'adjustment' => $locked->quantity = $quantity,
        };

        $locked->save();

        return $this->recordMovement($locked, $type, $quantity, $userId, $reason, $reference);
    }

    private function recordMovement(
        InventoryItem $item,
        string $type,
        int $quantity,
        int $userId,
        ?string $reason,
        ?string $reference,
    ): StockMovement {
        return StockMovement::create([
            'medicine_id' => $item->medicine_id,
            'inventory_item_id' => $item->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference' => $reference,
        ]);
    }
}
