<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ward extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_GENERAL = 'general';

    public const TYPE_ICU = 'icu';

    public const TYPE_MATERNITY = 'maternity';

    public const TYPE_PEDIATRIC = 'pediatric';

    public const TYPE_SURGICAL = 'surgical';

    public const TYPE_ISOLATION = 'isolation';

    protected $fillable = [
        'name',
        'code',
        'floor',
        'ward_type',
        'capacity',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function beds(): HasManyThrough
    {
        return $this->hasManyThrough(Bed::class, Room::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function activeAdmissions(): HasMany
    {
        return $this->admissions()->where('status', Admission::STATUS_ADMITTED);
    }
}
