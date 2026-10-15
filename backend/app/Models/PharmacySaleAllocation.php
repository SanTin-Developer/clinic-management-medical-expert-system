<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacySaleAllocation extends Model
{
    use HasFactory;

    protected $fillable = ['pharmacy_sale_item_id', 'inventory_item_id', 'stock_movement_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleItem::class, 'pharmacy_sale_item_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
