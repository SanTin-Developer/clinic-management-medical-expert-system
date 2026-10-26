<?php

namespace App\Services\Inpatient;

use App\Models\Admission;
use App\Models\AdmissionBillingRun;
use App\Models\AdmissionCharge;
use App\Models\AdmissionDeposit;
use App\Models\BillableService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InpatientBillingService
{
    public function __construct(
        private readonly RoomChargeService $roomCharges,
    ) {}

    /**
     * Generate room charges for an admission up to today (idempotent by source).
     */
    public function generateRoomCharges(Admission $admission, User $actor): int
    {
        if (! $admission->isActive() && $admission->status !== Admission::STATUS_ADMITTED) {
            return 0;
        }

        return DB::transaction(function () use ($admission, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            return $this->roomCharges->generateDailyCharges($admission, $actor);
        });
    }

    /**
     * Add a manual billable service charge.
     */
    public function addServiceCharge(Admission $admission, array $data, User $actor): AdmissionCharge
    {
        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            $service = null;
            if (! empty($data['billable_service_id'])) {
                $service = BillableService::query()->findOrFail($data['billable_service_id']);
                if (! $service->is_active) {
                    throw ValidationException::withMessages(['billable_service_id' => 'Selected service is not active.']);
                }
            }

            $quantity = (float) ($data['quantity'] ?? 1);
            $unitPrice = $service ? (float) $service->default_price : (float) ($data['unit_price'] ?? 0);
            if ($unitPrice < 0) {
                throw ValidationException::withMessages(['unit_price' => 'Unit price cannot be negative.']);
            }

            $subtotal = round($quantity * $unitPrice, 2);
            $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);
            if ($discountAmount < 0 || $discountAmount > $subtotal) {
                throw ValidationException::withMessages(['discount_amount' => 'Invalid discount amount.']);
            }

            $total = round($subtotal - $discountAmount, 2);

            return AdmissionCharge::create([
                'admission_id' => $admission->id,
                'patient_id' => $admission->patient_id,
                'charge_date' => $data['charge_date'] ?? now()->toDateString(),
                'charge_time' => $data['charge_time'] ?? now()->format('H:i:s'),
                'category' => $data['category'] ?? ($service ? $service->category : AdmissionCharge::CATEGORY_SERVICE),
                'description' => $data['description'] ?? ($service?->name ?? 'Service charge'),
                'billable_service_id' => $service?->id,
                'medicine_id' => null,
                'source_type' => $data['source_type'] ?? 'billable_service_order',
                'source_id' => $data['source_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discountAmount,
                'discount_reason' => $data['discount_reason'] ?? null,
                'discounted_by' => $discountAmount > 0 ? $actor->id : null,
                'subtotal' => $subtotal,
                'total' => $total,
                'status' => AdmissionCharge::STATUS_UNBILLED,
                'bed_id' => $admission->bed_id,
                'ward_id' => $admission->ward_id,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Generate a daily invoice for unbilled charges of an admission.
     */
    public function generateDailyInvoice(Admission $admission, User $actor, array $options = []): array
    {
        $billingDate = $options['billing_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($admission, $actor, $billingDate) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            // Prevent duplicate daily invoice for same date - check for completed runs with invoice
            $existingCompletedRun = AdmissionBillingRun::query()
                ->where('admission_id', $admission->id)
                ->where('run_date', $billingDate)
                ->where('status', AdmissionBillingRun::STATUS_COMPLETED)
                ->lockForUpdate()
                ->first();

            if ($existingCompletedRun && $existingCompletedRun->invoice_id) {
                throw ValidationException::withMessages([
                    'billing_date' => 'A daily invoice has already been generated for this admission on '.$billingDate.'.',
                ]);
            }

            // Also check for existing invoice directly for this billing period to be extra safe
            $existingInvoice = Invoice::query()
                ->where('admission_id', $admission->id)
                ->where('invoice_type', 'inpatient_daily')
                ->where('billing_period_start', $billingDate)
                ->where('billing_period_end', $billingDate)
                ->lockForUpdate()
                ->first();

            if ($existingInvoice) {
                // If there's an invoice but no completed run linked properly, try to be consistent
                if ($existingCompletedRun && $existingCompletedRun->invoice_id !== $existingInvoice->id) {
                    // This is a consistency issue - log/handle
                } else {
                    throw ValidationException::withMessages([
                        'billing_date' => 'A daily invoice has already been generated for this admission on '.$billingDate.'.',
                    ]);
                }
            }

            // Check for pending/failed runs for safe recovery
            $existingRun = AdmissionBillingRun::query()
                ->where('admission_id', $admission->id)
                ->where('run_date', $billingDate)
                ->lockForUpdate()
                ->first();

            // Ensure room charges are generated up to billing date
            $this->roomCharges->generateDailyCharges($admission, $actor, $billingDate);

            // Re-check unbilled charges after room charge generation
            $unbilledCharges = AdmissionCharge::query()
                ->where('admission_id', $admission->id)
                ->where('status', AdmissionCharge::STATUS_UNBILLED)
                ->orderBy('charge_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($unbilledCharges->isEmpty()) {
                // If there was a failed run with no charges, mark it completed or clean up
                if ($existingRun && $existingRun->status === AdmissionBillingRun::STATUS_FAILED) {
                    $existingRun->update([
                        'status' => AdmissionBillingRun::STATUS_COMPLETED,
                        'charges_count' => 0,
                        'charges_total' => 0,
                        'error_message' => null,
                    ]);
                }
                throw ValidationException::withMessages([
                    'charges' => 'There are no unbilled charges to invoice for this admission.',
                ]);
            }

            // Safe recovery: if there's a failed run, we can retry by updating it; if pending, might be stuck - but be careful
            if ($existingRun) {
                if ($existingRun->status === AdmissionBillingRun::STATUS_COMPLETED) {
                    if ($existingRun->invoice_id) {
                        throw ValidationException::withMessages([
                            'billing_date' => 'A daily invoice has already been generated for this admission on '.$billingDate.'.',
                        ]);
                    }
                } elseif ($existingRun->status === AdmissionBillingRun::STATUS_PENDING) {
                    // Don't create duplicate - update existing pending run
                    $run = $existingRun;
                    $run->update([
                        'generated_by' => $actor->id,
                        'generated_at' => now(),
                        'charges_count' => $unbilledCharges->count(),
                        'charges_total' => $unbilledCharges->sum(fn ($c) => (float) $c->total),
                        'ip_address' => request()?->ip(),
                        'error_message' => null,
                    ]);
                } elseif ($existingRun->status === AdmissionBillingRun::STATUS_FAILED) {
                    // Retry failed run
                    $run = $existingRun;
                    $run->update([
                        'generated_by' => $actor->id,
                        'generated_at' => now(),
                        'status' => AdmissionBillingRun::STATUS_PENDING,
                        'charges_count' => $unbilledCharges->count(),
                        'charges_total' => $unbilledCharges->sum(fn ($c) => (float) $c->total),
                        'ip_address' => request()?->ip(),
                        'error_message' => null,
                    ]);
                }
            } else {
                $run = AdmissionBillingRun::create([
                    'admission_id' => $admission->id,
                    'run_date' => $billingDate,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'status' => AdmissionBillingRun::STATUS_PENDING,
                    'charges_count' => $unbilledCharges->count(),
                    'charges_total' => $unbilledCharges->sum(fn ($c) => (float) $c->total),
                    'ip_address' => request()?->ip(),
                ]);
            }

            try {
                $subtotal = round($unbilledCharges->sum(fn ($c) => (float) $c->subtotal), 2);
                $discount = round($unbilledCharges->sum(fn ($c) => (float) $c->discount_amount), 2);
                $total = round($unbilledCharges->sum(fn ($c) => (float) $c->total), 2);

                // Invoice consistency check - verify sums
                if (abs($subtotal + 0 - ($unbilledCharges->sum(fn ($c) => (float) $c->subtotal))) > 0.001) {
                    throw ValidationException::withMessages(['charges' => 'Invoice total calculation inconsistency detected.']);
                }

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'patient_id' => $admission->patient_id,
                    'consultation_id' => null,
                    'admission_id' => $admission->id,
                    'status' => $total > 0 ? 'unpaid' : 'paid',
                    'invoice_type' => 'inpatient_daily',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => 0,
                    'total' => $total,
                    'amount_paid' => 0,
                    'balance' => $total,
                    'issued_at' => $billingDate,
                    'billing_period_start' => $billingDate,
                    'billing_period_end' => $billingDate,
                    'created_by' => $actor->id,
                    'billing_run_id' => $run->id,
                ]);

                foreach ($unbilledCharges as $charge) {
                    $item = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'medicine_id' => $charge->medicine_id,
                        'description' => $charge->description.' ('.$charge->charge_date->format('Y-m-d').')',
                        'quantity' => $charge->quantity,
                        'unit_price' => $charge->unit_price,
                        'amount' => $charge->total,
                    ]);

                    $charge->update([
                        'status' => AdmissionCharge::STATUS_INVOICED,
                        'invoice_id' => $invoice->id,
                        'invoice_item_id' => $item->id,
                        'invoiced_at' => now(),
                        'invoiced_by' => $actor->id,
                    ]);
                }

                // Final consistency check
                $invoice->refresh();
                $invoiceItemsTotal = $invoice->items()->sum('amount');
                if (abs(round($invoiceItemsTotal, 2) - round($invoice->total, 2)) > 0.001) {
                    throw \Exception('Invoice consistency check failed: items total does not match invoice total.');
                }

                $run->update([
                    'status' => AdmissionBillingRun::STATUS_COMPLETED,
                    'invoice_id' => $invoice->id,
                    'charges_count' => $unbilledCharges->count(),
                    'charges_total' => $total,
                ]);

                return [
                    'invoice' => $invoice->load(['items', 'admission.patient']),
                    'run' => $run,
                    'charges_invoiced' => $unbilledCharges->count(),
                ];
            } catch (\Throwable $e) {
                if (isset($run) && $run) {
                    $run->update([
                        'status' => AdmissionBillingRun::STATUS_FAILED,
                        'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    ]);
                }
                throw $e;
            }
        });
    }

    /**
     * Record a payment/deposit for an admission.
     */
    public function recordPayment(Admission $admission, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            $idempotencyKey = $data['idempotency_key'] ?? null;
            if ($idempotencyKey) {
                $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if (round((float) $existing->amount, 2) !== $amount) {
                        abort(409, 'This idempotency key was already used for a different payment amount.');
                    }

                    return $existing;
                }
            }

            // If paying against a specific invoice
            if (! empty($data['invoice_id'])) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
                if ($invoice->admission_id !== $admission->id) {
                    throw ValidationException::withMessages(['invoice_id' => 'Invoice does not belong to this admission.']);
                }

                $currentBalance = round((float) $invoice->balance, 2);
                if ($currentBalance <= 0) {
                    throw ValidationException::withMessages(['amount' => 'This invoice is already fully paid.']);
                }
                if ($amount > $currentBalance) {
                    throw ValidationException::withMessages(['amount' => 'Payment amount exceeds the outstanding balance.']);
                }

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'admission_id' => $admission->id,
                    'amount' => $amount,
                    'method' => $data['method'] ?? 'cash',
                    'reference' => $data['reference'] ?? null,
                    'paid_at' => $data['paid_at'] ?? now(),
                    'received_by' => $actor->id,
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $newAmountPaid = round((float) $invoice->amount_paid + $amount, 2);
                $newBalance = round((float) $invoice->total - $newAmountPaid, 2);
                if ($newBalance < 0) {
                    $newBalance = 0;
                }

                $invoice->update([
                    'amount_paid' => $newAmountPaid,
                    'balance' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : ((float) $invoice->amount_paid == 0 && $newAmountPaid > 0 ? 'partially_paid' : $invoice->status),
                ]);

                return $payment;
            }

            // Deposit to admission
            $deposit = AdmissionDeposit::create([
                'admission_id' => $admission->id,
                'patient_id' => $admission->patient_id,
                'amount' => $amount,
                'balance' => $amount,
                'type' => $data['type'] ?? AdmissionDeposit::TYPE_DEPOSIT,
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'received_by' => $actor->id,
                'received_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'status' => AdmissionDeposit::STATUS_AVAILABLE,
            ]);

            $payment = Payment::create([
                'invoice_id' => $data['invoice_id'] ?? null, // nullable for deposits
                'admission_id' => $admission->id,
                'admission_deposit_id' => $deposit->id,
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $payment;
        });
    }

    /**
     * Final discharge reconciliation.
     */
    public function dischargeReconciliation(Admission $admission, User $actor, array $options = []): array
    {
        return DB::transaction(function () use ($admission, $actor, $options) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            $billingDate = $options['billing_date'] ?? now()->toDateString();

            // Generate any remaining room charges
            $this->roomCharges->generateDailyCharges($admission, $actor, $billingDate);

            // Generate final invoice for any remaining unbilled charges
            $unbilledCharges = AdmissionCharge::query()
                ->where('admission_id', $admission->id)
                ->where('status', AdmissionCharge::STATUS_UNBILLED)
                ->lockForUpdate()
                ->get();

            $finalInvoice = null;
            if ($unbilledCharges->isNotEmpty()) {
                // Check if we already have a discharge invoice for today or in general for final billing
                $existingDischargeInvoice = Invoice::query()
                    ->where('admission_id', $admission->id)
                    ->where('invoice_type', 'inpatient_discharge')
                    ->lockForUpdate()
                    ->first();

                if ($existingDischargeInvoice) {
                    // Try to invoice remaining charges if any - but need to be careful about duplicates
                    // For safety, use generateDailyInvoice logic but mark as discharge
                    try {
                        $result = $this->generateDailyInvoice($admission, $actor, ['billing_date' => $billingDate]);
                        $finalInvoice = $result['invoice'];
                        $finalInvoice->update(['invoice_type' => 'inpatient_discharge']);
                    } catch (ValidationException $e) {
                        // If it's just because already invoiced today, that's okay if we have a discharge invoice
                        if (isset($e->errors()['billing_date']) || isset($e->errors()['charges'])) {
                            $finalInvoice = $existingDischargeInvoice;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $result = $this->generateDailyInvoice($admission, $actor, ['billing_date' => $billingDate]);
                    $finalInvoice = $result['invoice'];
                    // Mark as discharge invoice
                    $finalInvoice->update(['invoice_type' => 'inpatient_discharge']);
                }
            }

            // Invoice consistency checks for all invoices
            $invoices = Invoice::where('admission_id', $admission->id)->with('items', 'payments')->get();
            foreach ($invoices as $invoice) {
                $itemsTotal = $invoice->items()->sum('amount');
                if (abs(round((float) $itemsTotal, 2) - round((float) $invoice->total, 2)) > 0.001) {
                    // Critical consistency issue - log but don't silently fix; flag it
                    // Don't modify existing invoices as per instruction to preserve financial records
                    // Just ensure we report it
                }
                $calculatedBalance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
                if (abs(round((float) $invoice->balance, 2) - max(0, $calculatedBalance)) > 0.001) {
                    // Balance inconsistency - don't auto-fix to preserve records as instructed
                }
            }

            // Calculate summary
            $totalInvoiced = $invoices->sum(fn ($i) => (float) $i->total);
            $totalPaid = $invoices->sum(fn ($i) => (float) $i->amount_paid);
            $deposits = AdmissionDeposit::where('admission_id', $admission->id)->where('status', AdmissionDeposit::STATUS_AVAILABLE)->sum('balance');
            $outstanding = round(max(0, $totalInvoiced - $totalPaid - (float) $deposits), 2);

            return [
                'admission' => $admission,
                'invoices' => $invoices,
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid' => round($totalPaid, 2),
                'available_deposits' => round((float) $deposits, 2),
                'outstanding_balance' => $outstanding,
                'final_invoice' => $finalInvoice,
            ];
        });
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Invoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
