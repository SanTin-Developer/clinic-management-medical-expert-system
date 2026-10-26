<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillableService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'default_price',
        'is_active',
        'description',
        'department_id',
        'requires_approval',
        'price_source',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(AdmissionCharge::class);
    }
}
