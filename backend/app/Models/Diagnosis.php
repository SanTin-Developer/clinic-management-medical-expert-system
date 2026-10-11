<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diagnosis extends Model
{
    //
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'consultation_id',
        'patient_id',
        'doctor_id',
        'diagnosis_name',
        'description',
        'severity',
        'is_confirmed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_confirmed' => 'boolean',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
