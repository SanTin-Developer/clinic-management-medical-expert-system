<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_DISCHARGED = 'discharged';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'admission_number',
        'patient_id',
        'ward_id',
        'bed_id',
        'attending_doctor_id',
        'status',
        'admitted_at',
        'discharged_at',
        'reason',
        'diagnosis_summary',
        'notes',
        'created_by',
        'discharged_by',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function discharger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PatientAssignment::class);
    }

    public function nursingTasks(): HasMany
    {
        return $this->hasMany(NursingTask::class);
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ADMITTED;
    }
}
