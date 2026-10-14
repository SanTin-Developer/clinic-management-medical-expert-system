<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_GENERAL = 'general';

    public const TYPE_PRIVATE = 'private';

    public const TYPE_SEMI_PRIVATE = 'semi_private';

    public const TYPE_ISOLATION = 'isolation';

    protected $fillable = [
        'ward_id',
        'room_number',
        'room_type',
        'capacity',
        'price_per_day',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_day' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
