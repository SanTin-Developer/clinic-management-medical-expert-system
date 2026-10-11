<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disease extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_km',
        'category',
        'description',
        'severity_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class)
            ->withTimestamps();
    }

    public function expertRules(): HasMany
    {
        return $this->hasMany(ExpertRule::class);
    }
}
