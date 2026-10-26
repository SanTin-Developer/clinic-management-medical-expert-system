<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'consultation_id',
        'admission_id',
        'status',
        'invoice_type',
        'subtotal',
        'discount',
        'tax',
        'total',
        'amount_paid',
        'balance',
        'issued_at',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'notes',
        'created_by',
        'billing_run_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'issued_at' => 'date',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function pharmacySale(): HasOne
    {
        return $this->hasOne(PharmacySale::class);
    }

    public function admissionCharges(): HasMany
    {
        return $this->hasMany(AdmissionCharge::class);
    }

    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(AdmissionBillingRun::class, 'billing_run_id');
    }
}
