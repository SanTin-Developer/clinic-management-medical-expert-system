<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class VitalSign extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'nurse_id',
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'respiratory_rate',
        'oxygen_saturation',
        'weight',
        'height',
        'symptoms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:1',
            'oxygen_saturation' => 'decimal:2',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
