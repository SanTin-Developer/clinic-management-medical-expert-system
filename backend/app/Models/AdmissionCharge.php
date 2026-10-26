<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionCharge extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_UNBILLED = 'unbilled';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_CREDITED = 'credited';

    public const CATEGORY_ROOM = 'room';

    public const CATEGORY_MEDICINE = 'medicine';

    public const CATEGORY_SERVICE = 'service';

    public const CATEGORY_SUPPLY = 'supply';

    public const CATEGORY_NURSING = 'nursing';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'admission_id',
        'patient_id',
        'charge_date',
        'charge_time',
        'category',
        'description',
        'billable_service_id',
        'medicine_id',
        'source_type',
        'source_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'discount_reason',
        'discounted_by',
        'subtotal',
        'total',
        'status',
        'invoice_id',
        'invoice_item_id',
        'invoiced_at',
        'invoiced_by',
        'void_reason',
        'voided_by',
        'voided_at',
        'bed_id',
        'ward_id',
        'created_by',
        'corrected_by',
        'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'charge_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoicedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoiced_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function discountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discounted_by');
    }

    public function isUnbilled(): bool
    {
        return $this->status === self::STATUS_UNBILLED;
    }

    public function isInvoiced(): bool
    {
        return $this->status === self::STATUS_INVOICED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }
}
