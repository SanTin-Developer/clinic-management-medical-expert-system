<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'generic_name',
        'category',
        'form',
        'strength',
        'unit',
        'manufacturer',
        'description',
        'price',
        'reorder_level',
        'is_active',
        'requires_prescription',
        'is_recalled',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
            'requires_prescription' => 'boolean',
            'is_recalled' => 'boolean',
        ];
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function totalStock(): int
    {
        return (int) $this->inventoryItems()->sum('quantity');
    }
}
