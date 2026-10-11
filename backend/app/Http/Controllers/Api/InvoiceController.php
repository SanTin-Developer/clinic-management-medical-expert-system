<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::with(['patient', 'items']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($validated, $request) {
            $subtotal = collect($validated['items'])->sum(
                fn ($item) => $item['quantity'] * $item['unit_price']
            );
            $discount = (float) ($validated['discount'] ?? 0);
            $tax = (float) ($validated['tax'] ?? 0);
            $total = round($subtotal - $discount + $tax, 2);

            $invoice = Invoice::create([
                'invoice_number' => $this->generateNumber(),
                'patient_id' => $validated['patient_id'],
                'consultation_id' => $validated['consultation_id'] ?? null,
                'status' => $total <= 0 ? 'paid' : 'unpaid',
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'amount_paid' => 0,
                'balance' => $total,
                'issued_at' => now()->toDateString(),
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }

            return $invoice;
        });

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data' => $invoice->load(['patient', 'items']),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'data' => $invoice->load(['patient', 'consultation', 'createdBy', 'items', 'payments']),
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'A cancelled invoice cannot be modified.',
            ], 422);
        }

        $validated = $request->validate([
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $discount = (float) ($validated['discount'] ?? $invoice->discount);
        $tax = (float) ($validated['tax'] ?? $invoice->tax);

        $invoice->discount = $discount;
        $invoice->tax = $tax;
        $invoice->total = round($invoice->subtotal - $discount + $tax, 2);
        $invoice->balance = round($invoice->total - $invoice->amount_paid, 2);
        $invoice->status = $this->statusFor($invoice->total, (float) $invoice->amount_paid);

        if (array_key_exists('due_date', $validated)) {
            $invoice->due_date = $validated['due_date'];
        }

        if (array_key_exists('notes', $validated)) {
            $invoice->notes = $validated['notes'];
        }

        $invoice->save();

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'data' => $invoice->fresh()->load(['patient', 'items', 'payments']),
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'A cancelled invoice cannot receive payments.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ((float) $validated['amount'] > (float) $invoice->balance) {
            return response()->json([
                'message' => 'The payment amount exceeds the outstanding balance.',
            ], 422);
        }

        $payment = DB::transaction(function () use ($validated, $invoice, $request) {
            $payment = $invoice->payments()->create([
                'amount' => $validated['amount'],
                'method' => $validated['method'] ?? 'cash',
                'reference' => $validated['reference'] ?? null,
                'paid_at' => $validated['paid_at'] ?? now(),
                'received_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $amountPaid = round((float) $invoice->amount_paid + (float) $validated['amount'], 2);
            $balance = round((float) $invoice->total - $amountPaid, 2);

            $invoice->update([
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'status' => $this->statusFor((float) $invoice->total, $amountPaid),
            ]);

            return $payment;
        });

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => $payment->load('invoice'),
        ], 201);
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'Invoice is already cancelled.',
            ], 422);
        }

        if ((float) $invoice->amount_paid > 0) {
            return response()->json([
                'message' => 'An invoice with recorded payments cannot be cancelled.',
            ], 422);
        }

        $invoice->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Invoice cancelled successfully.',
            'data' => $invoice->fresh()->load(['patient', 'items']),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully.',
        ]);
    }

    /**
     * Derive the invoice status from its total and amount paid.
     */
    private function statusFor(float $total, float $amountPaid): string
    {
        if ($amountPaid <= 0 && $total > 0) {
            return 'unpaid';
        }

        if ($amountPaid >= $total) {
            return 'paid';
        }

        return 'partially_paid';
    }

    private function generateNumber(): string
    {
        do {
            $number = 'INV-'.random_int(100000, 999999);
        } while (Invoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
