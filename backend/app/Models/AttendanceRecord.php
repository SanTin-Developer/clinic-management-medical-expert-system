<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = ['present', 'late', 'absent', 'on_leave', 'holiday'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in',
        'clock_out',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Total hours between clock-in and clock-out, when both are present.
     */
    protected function hoursWorked(): Attribute
    {
        return Attribute::get(function (): ?float {
            if (! $this->clock_in || ! $this->clock_out) {
                return null;
            }

            return round($this->clock_in->diffInMinutes($this->clock_out) / 60, 2);
        });
    }

    /**
     * @var list<string>
     */
    protected $appends = ['hours_worked'];
}
