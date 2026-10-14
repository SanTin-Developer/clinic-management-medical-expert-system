<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only salary change history for staff members.
 * Access is restricted to staff.salary.* permissions and changes
 * are mirrored into security_events.
 */
class StaffSalaryHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'changed_by',
        'amount',
        'pay_frequency',
        'allowances',
        'effective_from',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allowances' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
