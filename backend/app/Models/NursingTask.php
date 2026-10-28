<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class NursingTask extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_VITAL_SIGNS = 'vital_signs';

    public const TYPE_MEDICATION = 'medication';

    public const TYPE_WOUND_CARE = 'wound_care';

    public const TYPE_MOBILITY = 'mobility';

    public const TYPE_HYGIENE = 'hygiene';

    public const TYPE_OBSERVATION = 'observation';

    public const TYPE_PROCEDURE = 'procedure';

    public const TYPE_EDUCATION = 'education';

    public const TYPE_OTHER = 'other';

    public const OUTCOME_COMPLETED = 'completed';

    public const OUTCOME_PARTIALLY_COMPLETED = 'partially_completed';

    public const OUTCOME_HELD = 'held';

    public const OUTCOME_REFUSED = 'refused';

    public const OUTCOME_NOT_COMPLETED = 'not_completed';

    /**
     * Task types whose completion requires a structured clinical record rather
     * than just an outcome and notes.
     */
    public const MEASUREMENT_TYPES = [
        self::TYPE_VITAL_SIGNS,
        self::TYPE_MEDICATION,
    ];

    protected $fillable = [
        'patient_id',
        'admission_id',
        'assigned_nurse_id',
        'task_type',
        'title',
        'description',
        'priority',
        'scheduled_at',
        'due_at',
        'completed_at',
        'overdue_notified_at',
        'completed_by',
        'status',
        'outcome',
        'outcome_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('scheduled_at', $date);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function assignedNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_nurse_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function vitalSign(): HasOne
    {
        return $this->hasOne(VitalSign::class, 'nursing_task_id');
    }

    public function medicationAdministration(): HasOne
    {
        return $this->hasOne(MedicationAdministration::class, 'nursing_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function requiresVitalSigns(): bool
    {
        return $this->task_type === self::TYPE_VITAL_SIGNS;
    }

    public function requiresMedicationAdministration(): bool
    {
        return $this->task_type === self::TYPE_MEDICATION;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->status !== self::STATUS_CANCELLED
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
