<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\PharmacySale;
use App\Services\Pharmacy\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PharmacyPosController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('inventory.view'), 403);
        $query = Medicine::query()
            ->where('is_active', true)
            ->withSum('inventoryItems as stock', 'quantity')
            ->withSum([
                'inventoryItems as usable_stock' => fn ($inventoryQuery) => $inventoryQuery
                    ->where('quantity', '>', 0)
                    ->where(fn ($expiryQuery) => $expiryQuery
                        ->whereNull('expiration_date')
                        ->orWhereDate('expiration_date', '>=', now()->toDateString())),
            ], 'quantity');

        if (Schema::hasColumn('medicines', 'is_recalled')) {
            $query->where('is_recalled', false);
        }

        if ($search = $request->query('search')) {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($searchQuery) use ($term) {
                foreach (['name', 'code', 'category', 'strength', 'form'] as $column) {
                    $searchQuery->orWhereRaw("lower({$column}) like ?", [$term]);
                }
            });
        }

        $products = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        $products->through(function (Medicine $medicine): Medicine {
            $medicine->setAttribute('stock_status', (int) $medicine->usable_stock > 0 ? 'available' : 'unavailable');
            $medicine->setAttribute('unavailable_reason', (int) $medicine->usable_stock > 0 ? null : 'No usable, unexpired stock is available.');

            return $medicine;
        });

        return response()->json($products);
    }

    public function store(Request $request, InventoryService $inventory): JsonResponse
    {
        $this->authorize('create', PharmacySale::class);
        abort_unless(
            Schema::hasTable('pharmacy_sales') && Schema::hasColumn('medicines', 'requires_prescription'),
            503,
            'Pharmacy POS is unavailable until its database migrations are applied.'
        );

        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'patient_id' => ['nullable', 'exists:patients,id'], 'discount' => ['nullable', 'numeric', 'min:0'], 'payment' => ['nullable', 'array'], 'payment.amount' => ['nullable', 'numeric', 'min:0.01'], 'payment.amount_received' => ['nullable', 'numeric', 'min:0.01'], 'payment.method' => ['nullable', 'in:cash,card,bank_transfer,insurance,other'], 'items' => ['required', 'array', 'min:1'], 'items.*.medicine_id' => ['required', 'exists:medicines,id'], 'items.*.quantity' => ['required', 'integer', 'min:1']]);
        if ($existing = PharmacySale::where('idempotency_key', $data['idempotency_key'])->first()) {
            return response()->json(['data' => $existing->load('invoice.payments', 'items.medicine'), 'duplicate' => true]);
        }
        try {
            $sale = DB::transaction(function () use ($data, $request, $inventory) {
                $medicines = Medicine::whereIn('id', collect($data['items'])->pluck('medicine_id'))->lockForUpdate()->get()->keyBy('id');
                $subtotal = 0;
                foreach ($data['items'] as $line) {
                    $medicine = $medicines[$line['medicine_id']];
                    if (! $medicine->is_active || $medicine->is_recalled || $medicine->requires_prescription) {
                        abort(422, 'Medicine is not eligible for POS sale.');
                    } $subtotal += (float) $medicine->price * $line['quantity'];
                }
                $discount = (float) ($data['discount'] ?? 0);
                if ($discount > $subtotal) {
                    abort(422, 'Discount cannot exceed subtotal.');
                }
                $sale = PharmacySale::create(['sale_number' => 'POS-'.strtoupper(bin2hex(random_bytes(4))), 'idempotency_key' => $data['idempotency_key'], 'patient_id' => $data['patient_id'] ?? null, 'sold_by' => $request->user()->id, 'status' => 'completed', 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount]);
                $invoice = Invoice::create(['invoice_number' => 'INV-POS-'.strtoupper(bin2hex(random_bytes(4))), 'patient_id' => $data['patient_id'] ?? null, 'status' => 'unpaid', 'subtotal' => $subtotal, 'discount' => $discount, 'tax' => 0, 'total' => $subtotal - $discount, 'amount_paid' => 0, 'balance' => $subtotal - $discount, 'issued_at' => now(), 'created_by' => $request->user()->id]);
                $sale->update(['invoice_id' => $invoice->id]);
                foreach ($data['items'] as $line) {
                    $medicine = $medicines[$line['medicine_id']];
                    $item = $sale->items()->create(['medicine_id' => $medicine->id, 'quantity' => $line['quantity'], 'unit_price' => $medicine->price, 'discount' => 0, 'total' => $medicine->price * $line['quantity']]);
                    $invoice->items()->create(['description' => $medicine->name, 'quantity' => $line['quantity'], 'unit_price' => $medicine->price, 'amount' => $medicine->price * $line['quantity']]);
                    foreach ($inventory->allocate($medicine, $line['quantity'], $request->user()->id, 'out', 'POS sale', $sale->sale_number) as $allocation) {
                        $item->allocations()->create(['inventory_item_id' => $allocation['batch']->id, 'stock_movement_id' => $allocation['movement']->id, 'quantity' => $allocation['quantity']]);
                    }
                }
                if (isset($data['payment']['amount'])) {
                    $amount = (float) $data['payment']['amount'];
                    if ($amount !== (float) $sale->total) {
                        abort(422, 'Payment must equal the server-calculated sale total.');
                    }
                    if (isset($data['payment']['amount_received']) && (float) $data['payment']['amount_received'] < $amount) {
                        abort(422, 'Amount received must cover the server-calculated sale total.');
                    }
                    $invoice->payments()->create(['amount' => $amount, 'method' => $data['payment']['method'] ?? 'cash', 'paid_at' => now(), 'received_by' => $request->user()->id]);
                    $invoice->update(['amount_paid' => $amount, 'balance' => 0, 'status' => 'paid']);
                }

                return $sale;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 422 ? 422 : 500);
        }

        return response()->json(['data' => $sale->load('invoice.payments', 'items.medicine', 'items.allocations.inventoryItem')], 201);
    }
}
