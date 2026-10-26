<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Medicine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $base = Invoice::with(['patient', 'items']);
        $query = clone $base;

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $invoices = $query->latest()->paginate(15);

        // One grouped query for every status bucket, replacing one count/sum per status.
        $byStatus = (clone $base)
            ->reorder()
            ->selectRaw('status, count(*) as invoice_count, sum(total) as status_total, sum(balance) as status_balance')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusCount = fn (string $status): int => (int) ($byStatus[$status]->invoice_count ?? 0);

        $totalRevenue = 0.0;
        $outstanding = 0.0;

        foreach ($byStatus as $status => $row) {
            if ($status === null || $status === 'cancelled') {
                continue;
            }

            $totalRevenue += (float) $row->status_total;

            if (in_array($status, ['unpaid', 'partially_paid'], true)) {
                $outstanding += (float) $row->status_balance;
            }
        }

        $stats = [
            'total' => $invoices->total(),
            'unpaid' => $statusCount('unpaid'),
            'partially_paid' => $statusCount('partially_paid'),
            'paid' => $statusCount('paid'),
            'cancelled' => $statusCount('cancelled'),
            'total_revenue' => round($totalRevenue, 2),
            'outstanding' => round($outstanding, 2),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($invoices->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'amount_paid' => ['prohibited'],
            'balance' => ['prohibited'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['prohibited'],
            'items.*.amount' => ['prohibited'],
        ]);

        $invoice = DB::transaction(function () use ($validated, $request) {
            $medicineIds = collect($validated['items'])->pluck('medicine_id')->unique()->values();

            $medicines = Medicine::whereIn('id', $medicineIds)->lockForUpdate()->get()->keyBy('id');

            $lines = collect($validated['items'])->map(function (array $item) use ($medicines): array {
                $medicine = $medicines->get($item['medicine_id']);

                if (! $medicine) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected medicines no longer exist.',
                    ]);
                }

                if (! $medicine->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "Medicine \"{$medicine->name}\" is inactive and cannot be billed.",
                    ]);
                }

                if ($medicine->is_recalled) {
                    throw ValidationException::withMessages([
                        'items' => "Medicine \"{$medicine->name}\" has been recalled and cannot be billed.",
                    ]);
                }

                $unitPrice = (float) $medicine->price;

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Medicine \"{$medicine->name}\" does not have a valid billable price.",
                    ]);
                }

                $quantity = (int) $item['quantity'];

                return [
                    'medicine_id' => $medicine->id,
                    'description' => $medicine->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => round($quantity * $unitPrice, 2),
                ];
            });

            $subtotal = round((float) $lines->sum('amount'), 2);
            $discount = round((float) ($validated['discount'] ?? 0), 2);
            $tax = 0.0;

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed the invoice subtotal.',
                ]);
            }

            $total = round($subtotal - $discount + $tax, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'total' => 'Invoice total must be greater than zero.',
                ]);
            }

            $invoice = Invoice::create([
                'invoice_number' => $this->generateNumber(),
                'patient_id' => $validated['patient_id'],
                'consultation_id' => $validated['consultation_id'] ?? null,
                'status' => 'unpaid',
                'subtotal' => $subtotal,
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

            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }

            return $invoice;
        });

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data' => $invoice->load(['patient', 'items.medicine']),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'data' => $invoice->load(['patient', 'consultation', 'cashier', 'items', 'payments.receiver']),
        ]);
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['patient', 'cashier', 'items', 'payments.receiver']);
        $sale = null;
        if (\Schema::hasTable('pharmacy_sales')) {
            $sale = $invoice->pharmacySale()->with('items.medicine')->first();
        }

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'sale' => $sale,
            'clinicName' => config('app.name'),
        ])->setPaper('a4');

        return $pdf->download('RoyalCare-Invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($request->exists('items')) {
            return response()->json([
                'message' => 'Invoice items are read-only and cannot be modified.',
            ], 422);
        }

        $validated = $request->validate([
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0', 'max:0'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'amount_paid' => ['prohibited'],
            'balance' => ['prohibited'],
        ]);

        $invoice = DB::transaction(function () use ($request, $invoice, $validated) {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                abort(422, 'A cancelled invoice cannot be modified.');
            }

            if ($request->exists('items')) {
                abort(422, 'Invoice items are read-only and cannot be modified.');
            }

            $subtotal = round((float) $lockedInvoice->items()->sum('amount'), 2);
            $discount = round((float) ($validated['discount'] ?? $lockedInvoice->discount), 2);
            $tax = (float) $lockedInvoice->tax;

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed the invoice subtotal.',
                ]);
            }

            $total = round($subtotal - $discount + $tax, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'total' => 'Invoice total must be greater than zero.',
                ]);
            }

            if ($total < (float) $lockedInvoice->amount_paid) {
                throw ValidationException::withMessages([
                    'total' => 'Invoice total cannot be less than the amount already paid.',
                ]);
            }

            $lockedInvoice->subtotal = $subtotal;
            $lockedInvoice->discount = $discount;
            $lockedInvoice->tax = $tax;
            $lockedInvoice->total = $total;
            $lockedInvoice->balance = round($total - (float) $lockedInvoice->amount_paid, 2);
            $lockedInvoice->status = $this->statusFor($total, (float) $lockedInvoice->amount_paid);

            if (array_key_exists('due_date', $validated)) {
                $lockedInvoice->due_date = $validated['due_date'];
            }

            if (array_key_exists('notes', $validated)) {
                $lockedInvoice->notes = $validated['notes'];
            }

            $lockedInvoice->save();

            return $lockedInvoice;
        });

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'data' => $invoice->fresh()->load(['patient', 'items.medicine', 'payments']),
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'insurance', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('payments', 'idempotency_key')) {
            $rules['idempotency_key'] = ['nullable', 'uuid'];
        }

        $validated = $request->validate($rules);

        $amount = round((float) $validated['amount'], 2);
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        [$payment, $duplicate] = DB::transaction(function () use ($validated, $invoice, $request, $amount, $idempotencyKey) {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($idempotencyKey !== null && \Illuminate\Support\Facades\Schema::hasColumn('payments', 'idempotency_key')) {
                $existingPayment = $lockedInvoice->payments()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingPayment !== null) {
                    if (round((float) $existingPayment->amount, 2) !== $amount) {
                        abort(409, 'This idempotency key was already used for a different payment amount.');
                    }

                    return [$existingPayment, true];
                }
            }

            if ($lockedInvoice->status === 'cancelled') {
                abort(422, 'A cancelled invoice cannot receive payments.');
            }

            $total = round((float) $lockedInvoice->total, 2);
            $currentAmountPaid = round((float) $lockedInvoice->amount_paid, 2);
            $currentBalance = round((float) $lockedInvoice->balance, 2);

            if ($total <= 0) {
                abort(422, 'This invoice cannot receive payments.');
            }

            if ($currentBalance <= 0) {
                abort(422, 'This invoice is already fully paid.');
            }

            if ($amount > $currentBalance) {
                abort(422, 'The payment amount exceeds the outstanding balance.');
            }

            $paymentData = [
                'amount' => $amount,
                'method' => $validated['method'] ?? 'cash',
                'reference' => $validated['reference'] ?? null,
                'paid_at' => $validated['paid_at'] ?? now(),
                'received_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ];

            if ($idempotencyKey !== null && \Illuminate\Support\Facades\Schema::hasColumn('payments', 'idempotency_key')) {
                $paymentData['idempotency_key'] = $idempotencyKey;
            }

            $payment = $lockedInvoice->payments()->create($paymentData);

            $newAmountPaid = round($currentAmountPaid + $amount, 2);
            $newBalance = round($total - $newAmountPaid, 2);

            if ($newBalance < 0) {
                $newBalance = 0.00;
            }

            $lockedInvoice->update([
                'amount_paid' => $newAmountPaid,
                'balance' => $newBalance,
                'status' => $this->statusFor($total, $newAmountPaid),
            ]);

            return [$payment, false];
        });

        $response = [
            'message' => $duplicate ? 'Payment already recorded.' : 'Payment recorded successfully.',
            'data' => $payment->load('invoice'),
        ];

        if ($duplicate) {
            $response['duplicate'] = true;
        }

        return response()->json($response, $duplicate ? 200 : 201);
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice = DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                abort(422, 'Invoice is already cancelled.');
            }

            if ((float) $lockedInvoice->amount_paid > 0) {
                abort(422, 'An invoice with recorded payments cannot be cancelled.');
            }

            $lockedInvoice->update(['status' => 'cancelled']);

            return $lockedInvoice;
        });

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
     * List billable medicines for invoice line selection.
     */
    public function medicines(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $query = Medicine::query()
            ->where('is_active', true)
            ->where('is_recalled', false)
            ->where('price', '>', 0);

        if ($search = $request->query('search')) {
            $term = '%'.strtolower($search).'%';

            $query->where(function ($searchQuery) use ($term) {
                foreach (['name', 'code', 'generic_name', 'strength', 'form'] as $column) {
                    $searchQuery->orWhereRaw("lower({$column}) like ?", [$term]);
                }
            });
        }

        $perPage = min(max($request->integer('per_page', 50), 1), 100);

        $medicines = $query
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Medicine $medicine) => [
                'id' => $medicine->id,
                'code' => $medicine->code,
                'name' => $medicine->name,
                'generic_name' => $medicine->generic_name,
                'form' => $medicine->form,
                'strength' => $medicine->strength,
                'unit' => $medicine->unit,
                'price' => $medicine->price,
            ]);

        return response()->json($medicines);
    }

    /**
     * Derive the invoice status from its total and amount paid.
     */
    private function statusFor(float $total, float $amountPaid): string
    {
        if ($total <= 0 || $amountPaid <= 0) {
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
