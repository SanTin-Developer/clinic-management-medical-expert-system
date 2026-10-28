<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionCharge;
use App\Models\AdmissionDeposit;
use App\Models\Invoice;
use App\Services\Inpatient\InpatientBillingService;
use App\Services\Inpatient\RoomChargeService;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdmissionBillingController extends Controller
{
    public function __construct(
        private readonly InpatientBillingService $billing,
        private readonly RoomChargeService $roomCharges,
        private readonly PatientAccessService $access,
    ) {}

    public function ledger(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('view', $admission);
        abort_unless($request->user()->can('inpatient.billing.view'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $charges = AdmissionCharge::where('admission_id', $admission->id)
            ->with(['billableService', 'medicine', 'invoice', 'createdBy'])
            ->orderBy('charge_date')
            ->orderBy('id')
            ->get();

        $invoices = Invoice::where('admission_id', $admission->id)
            ->with(['items', 'payments'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        $deposits = AdmissionDeposit::where('admission_id', $admission->id)
            ->with('receivedBy')
            ->orderByDesc('received_at')
            ->get();

        $unbilledTotal = $charges->where('status', AdmissionCharge::STATUS_UNBILLED)->sum(fn ($c) => (float) $c->total);
        $invoicedTotal = $charges->where('status', AdmissionCharge::STATUS_INVOICED)->sum(fn ($c) => (float) $c->total);
        $totalInvoiced = $invoices->sum(fn ($i) => (float) $i->total);
        $totalPaid = $invoices->sum(fn ($i) => (float) $i->amount_paid);
        $availableDeposits = $deposits->where('status', AdmissionDeposit::STATUS_AVAILABLE)->sum(fn ($d) => (float) $d->balance);

        return response()->json([
            'admission' => $admission->load(['patient', 'ward', 'bed', 'attendingDoctor']),
            'charges' => $charges,
            'invoices' => $invoices,
            'deposits' => $deposits,
            'summary' => [
                'unbilled_total' => round($unbilledTotal, 2),
                'invoiced_total' => round($invoicedTotal, 2),
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid' => round($totalPaid, 2),
                'available_deposits' => round($availableDeposits, 2),
                'outstanding_balance' => round(max(0, $totalInvoiced - $totalPaid - $availableDeposits), 2),
            ],
        ]);
    }

    public function generateRoomCharges(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $count = $this->billing->generateRoomCharges($admission, $request->user());

        return response()->json([
            'message' => $count > 0 ? "Generated {$count} room charge(s)." : 'No new room charges to generate.',
            'count' => $count,
        ]);
    }

    public function addCharge(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $validated = $request->validate([
            'billable_service_id' => ['nullable', 'integer', 'exists:billable_services,id'],
            'category' => ['nullable', 'string', Rule::in(['room', 'medicine', 'service', 'supply', 'nursing', 'other'])],
            'description' => ['nullable', 'string', 'max:500'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'charge_date' => ['nullable', 'date'],
            'charge_time' => ['nullable', 'date_format:H:i:s'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
        ]);

        $charge = $this->billing->addServiceCharge($admission, $validated, $request->user());

        return response()->json([
            'message' => 'Charge added successfully.',
            'data' => $charge->load(['billableService', 'createdBy']),
        ], 201);
    }

    public function generateDailyInvoice(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $validated = $request->validate([
            'billing_date' => ['nullable', 'date'],
        ]);

        $result = $this->billing->generateDailyInvoice($admission, $request->user(), $validated);

        return response()->json([
            'message' => 'Daily invoice generated successfully.',
            'data' => $result,
        ], 201);
    }

    public function recordPayment(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'type' => ['nullable', Rule::in(['deposit', 'credit', 'adjustment'])],
        ];

        if (Schema::hasColumn('payments', 'idempotency_key')) {
            $rules['idempotency_key'] = ['nullable', 'uuid'];
        }

        $validated = $request->validate($rules);

        $payment = $this->billing->recordPayment($admission, $validated, $request->user());

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => $payment->load(['invoice', 'admissionDeposit']),
        ], 201);
    }

    public function dischargeReconciliation(Request $request, Admission $admission): JsonResponse
    {
        $this->authorize('update', $admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);
        if ($this->access->isRestricted($request->user())) {
            abort_unless($this->access->canAccessPatient($request->user(), $admission->patient_id), 403);
        }

        $validated = $request->validate([
            'billing_date' => ['nullable', 'date'],
        ]);

        $result = $this->billing->dischargeReconciliation($admission, $request->user(), $validated);

        return response()->json([
            'message' => 'Discharge reconciliation completed.',
            'data' => $result,
        ]);
    }

    public function voidCharge(Request $request, AdmissionCharge $charge): JsonResponse
    {
        $this->authorize('update', $charge->admission);
        abort_unless($request->user()->can('inpatient.billing.manage'), 403);

        if ($charge->status === AdmissionCharge::STATUS_INVOICED) {
            return response()->json(['message' => 'Cannot void a charge that has already been invoiced. Use a credit note.'], 422);
        }

        if ($charge->status === AdmissionCharge::STATUS_VOIDED) {
            return response()->json(['message' => 'Charge is already voided.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $charge->update([
            'status' => AdmissionCharge::STATUS_VOIDED,
            'void_reason' => $validated['reason'],
            'voided_by' => $request->user()->id,
            'voided_at' => now(),
        ]);

        return response()->json(['message' => 'Charge voided.', 'data' => $charge->fresh()]);
    }
}
