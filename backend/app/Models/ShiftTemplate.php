<?php

namespace App\Models;

use Database\Factories\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable shift definition (Morning, Evening, Night Watch). Times are
 * authored in the clinic timezone; use App\Services\Attendance\ShiftSchedule to
 * translate them to absolute UTC instants.
 */
class ShiftTemplate extends Model
{
    /** @use HasFactory<ShiftTemplateFactory> */
    use HasFactory;

    public const TYPES = ['day', 'evening', 'night', 'on_call'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'start_time',
        'clock_in_cutoff',
        'end_time',
        'crosses_midnight',
        'clock_out_opens_at',
        'clock_out_closes_at',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
