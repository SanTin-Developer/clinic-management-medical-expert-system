<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'shift_id',
        'shift_template_id',
        'work_date',
        'clock_in',
        'clock_out',
        'clock_in_session_id',
        'clock_out_session_id',
        'scheduled_start',
        'scheduled_end',
        'status',
        'late_minutes',
        'late_band',
        'clock_in_source',
        'clock_out_source',
        'missing_clock_out',
        'finalized_at',
        'corrected_by',
        'corrected_at',
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
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'corrected_at' => 'datetime',
            'late_minutes' => 'integer',
            'missing_clock_out' => 'boolean',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
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
