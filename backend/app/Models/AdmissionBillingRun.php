<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionBillingRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'admission_id',
        'run_date',
        'generated_by',
        'generated_at',
        'invoice_id',
        'charges_count',
        'charges_total',
        'status',
        'error_message',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'generated_at' => 'datetime',
            'charges_count' => 'integer',
            'charges_total' => 'decimal:2',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
