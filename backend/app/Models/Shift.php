<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const TYPES = ['day', 'evening', 'night', 'on_call'];

    /**
     * @var list<string>
     */
    public const STATUSES = ['scheduled', 'completed', 'cancelled'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'shift_template_id',
        'shift_date',
        'start_time',
        'end_time',
        'crosses_midnight',
        'type',
        'status',
        'published_at',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'crosses_midnight' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
