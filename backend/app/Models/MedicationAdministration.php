<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Medication Administration Record (MAR) entry. This records that a
 * medication was (or was not) given to an inpatient. It intentionally does NOT
 * touch pharmacy inventory: stock is decremented exactly once when a
 * prescription is dispensed.
 */
class MedicationAdministration extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ADMINISTERED = 'administered';

    public const STATUS_HELD = 'held';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_MISSED = 'missed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'patient_id',
        'admission_id',
        'prescription_id',
        'prescription_item_id',
        'medicine_id',
        'ordered_by',
        'administered_by',
        'dose',
        'route',
        'scheduled_at',
        'administered_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'administered_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function isGiven(): bool
    {
        return $this->status === self::STATUS_ADMINISTERED;
    }
}
