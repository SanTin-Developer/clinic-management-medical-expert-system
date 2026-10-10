<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'disease_id',
        'explanation',
        'priority',
        'confidence_score',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'confidence_score' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(ExpertRuleCondition::class);
    }
}
