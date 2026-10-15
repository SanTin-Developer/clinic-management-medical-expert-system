<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A clinic holiday. Absence finalization skips these dates.
 */
class ClinicHoliday extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'holiday_date',
        'name',
        'is_recurring',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }
}
