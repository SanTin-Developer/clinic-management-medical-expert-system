<?php

namespace App\Models;

use Database\Factories\QueueEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    /** @use HasFactory<QueueEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = [
        'waiting',
        'called',
        'serving',
        'done',
        'skipped',
        'cancelled',
    ];

    /**
     * @var list<string>
     */
    public const PRIORITIES = ['normal', 'urgent'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'doctor_id',
        'ticket_number',
        'status',
        'priority',
        'queue_date',
        'called_at',
        'started_at',
        'completed_at',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Active (not yet finished) entries appear before finished ones, and
     * urgent cases float to the top so they can be triaged first.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'called' THEN 1 WHEN 'serving' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 ELSE 1 END")
            ->orderBy('created_at');
    }
}
