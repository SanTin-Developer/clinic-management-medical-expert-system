<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionDeposit extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'admission_id',
        'patient_id',
        'amount',
        'balance',
        'type',
        'method',
        'reference',
        'received_by',
        'received_at',
        'notes',
        'applied_to_invoice_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function appliedToInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'applied_to_invoice_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && (float) $this->balance > 0;
    }
}
