<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Symptom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class)
            ->withTimestamps();
    }

    public function expertRuleConditions(): HasMany
    {
        return $this->hasMany(ExpertRuleCondition::class);
    }
}
