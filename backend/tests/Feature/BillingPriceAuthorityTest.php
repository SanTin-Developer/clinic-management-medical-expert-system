<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingPriceAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsReceptionist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Receptionist');
        Sanctum::actingAs($user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function medicine(array $overrides = []): Medicine
    {
        return Medicine::factory()->create(array_merge([
            'price' => 40,
            'is_active' => true,
            'is_recalled' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Medicine $medicine, int $quantity = 1, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => $quantity],
            ],
        ], $overrides);
    }

    private function createInvoice(Medicine $medicine, int $quantity = 1): Invoice
    {
        $response = $this->postJson('/api/v1/invoices', $this->payload($medicine, $quantity))
            ->assertCreated();

        return Invoice::findOrFail($response->json('data.id'));
    }

    public function test_client_supplied_unit_price_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 40]);

        $payload = $this->payload($medicine);
        $payload['items'][0]['unit_price'] = 1;

        $this->postJson('/api/v1/invoices', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.unit_price');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_client_supplied_amounts_totals_and_balances_are_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 40]);

        $payload = $this->payload($medicine, 1, [
            'subtotal' => 1,
            'total' => 1,
            'amount_paid' => 1,
            'balance' => 0,
        ]);
        $payload['items'][0]['amount'] = 1;

        $this->postJson('/api/v1/invoices', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'subtotal',
                'total',
                'amount_paid',
                'balance',
                'items.0.amount',
            ]);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_free_text_item_without_medicine_is_rejected(): void
    {
        $this->actingAsReceptionist();

        $this->postJson('/api/v1/invoices', [
            'patient_id' => Patient::factory()->create()->id,
            'items' => [
                ['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 50],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.medicine_id');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_server_uses_medicine_price_and_stores_line_snapshot(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['name' => 'Paracetamol 500mg', 'price' => 75]);

        $response = $this->postJson('/api/v1/invoices', $this->payload($medicine, 2))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '150.00')
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.balance', '150.00')
            ->assertJsonPath('data.amount_paid', '0.00')
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.items.0.medicine_id', $medicine->id)
            ->assertJsonPath('data.items.0.description', 'Paracetamol 500mg')
            ->assertJsonPath('data.items.0.unit_price', '75.00')
            ->assertJsonPath('data.items.0.amount', '150.00');

        $this->assertDatabaseCount('payments', 0);

        $this->assertNotNull($response->json('data.id'));
    }

    public function test_inactive_medicine_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['is_active' => false]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_recalled_medicine_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['is_recalled' => true]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_medicine_with_non_positive_price_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 0]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_non_positive_quantity_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine();

        $this->postJson('/api/v1/invoices', $this->payload($medicine, 0))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');

        $this->postJson('/api/v1/invoices', $this->payload($medicine, -1))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_discount_greater_than_subtotal_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine, 1, ['discount' => 100.01]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_total_of_zero_is_rejected_and_never_marked_paid(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine, 1, ['discount' => 100]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('total');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_zero_client_tax_is_rejected(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);

        $this->postJson('/api/v1/invoices', $this->payload($medicine, 1, ['tax' => 10]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tax');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_new_invoice_is_unpaid_until_a_payment_is_recorded(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);

        $invoice = $this->createInvoice($medicine);

        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertSame(0, $invoice->payments()->count());

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 40])
            ->assertCreated();

        $this->assertSame('partially_paid', $invoice->fresh()->status);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 60])
            ->assertCreated();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_payment_rejects_zero_and_negative_amounts(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => -10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }

    public function test_payment_rejects_on_cancelled_invoice(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->postJson("/api/v1/invoices/{$invoice->id}/cancel")->assertOk();

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 50])
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_rejects_on_fully_paid_invoice(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 100])
            ->assertCreated();

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 1])
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_repeated_payment_with_same_idempotency_key_creates_one_payment(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);
        $payload = [
            'amount' => 40,
            'method' => 'cash',
            'idempotency_key' => 'b6dbf1c5-0aeb-49e4-97e3-2ea9140c1c1a',
        ];

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)
            ->assertCreated()
            ->assertJsonPath('data.amount', '40.00');

        $paymentId = $response->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('data.id', $paymentId);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'invoice_id' => $invoice->id,
            'amount' => 40,
            'received_by' => auth()->id(),
        ]);
        $this->assertSame('40.00', $invoice->fresh()->amount_paid);
        $this->assertSame('60.00', $invoice->fresh()->balance);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
    }

    public function test_repeated_payment_with_same_idempotency_key_succeeds_after_invoice_is_paid(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);
        $payload = [
            'amount' => 100,
            'idempotency_key' => '8304d30f-0479-4e11-8e43-ff3a1f585f3a',
        ];

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)
            ->assertCreated();

        $paymentId = $response->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('data.id', $paymentId);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('100.00', $invoice->fresh()->amount_paid);
        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_reused_idempotency_key_with_different_amount_returns_409(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);
        $idempotencyKey = '6f7d6f3e-3f15-4c4d-8dbf-6a7b5e1a2c3d';

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 40,
            'idempotency_key' => $idempotencyKey,
        ])->assertCreated();

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 50,
            'idempotency_key' => $idempotencyKey,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This idempotency key was already used for a different payment amount.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('40.00', $invoice->fresh()->amount_paid);
        $this->assertSame('60.00', $invoice->fresh()->balance);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
    }

    public function test_non_uuid_idempotency_key_returns_422(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 40,
            'idempotency_key' => 'not-a-uuid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }

    public function test_received_by_is_ignored_and_taken_from_auth_user(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $otherUser = User::factory()->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 25,
            'received_by' => $otherUser->id,
        ])->assertCreated();

        $paymentId = $response->json('data.id');
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'received_by' => auth()->id(),
        ]);
        $this->assertDatabaseMissing('payments', [
            'id' => $paymentId,
            'received_by' => $otherUser->id,
        ]);
    }

    public function test_update_rejects_item_edits(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 5],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invoice items are read-only and cannot be modified.');

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('100.00', $invoice->fresh()->total);
    }

    public function test_update_rejects_non_zero_tax_and_excess_discount(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['tax' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tax');

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['discount' => 100.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');

        $this->assertSame('100.00', $invoice->fresh()->total);
    }

    public function test_update_recomputes_totals_and_keeps_unpaid_status(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['discount' => 10])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount', '10.00')
            ->assertJsonPath('data.total', '90.00')
            ->assertJsonPath('data.balance', '90.00')
            ->assertJsonPath('data.status', 'unpaid');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_update_cannot_lower_total_below_amount_paid(): void
    {
        $this->actingAsReceptionist();
        $medicine = $this->medicine(['price' => 100]);
        $invoice = $this->createInvoice($medicine);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 50])
            ->assertCreated();

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['discount' => 60])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total');

        $this->assertSame('100.00', $invoice->fresh()->total);
    }

    public function test_legacy_invoice_without_medicine_id_remains_readable(): void
    {
        $this->actingAsReceptionist();

        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'medicine_id' => null,
            'description' => 'Legacy free-text item',
            'quantity' => 1,
            'unit_price' => 25,
            'amount' => 25,
        ]);

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.description', 'Legacy free-text item')
            ->assertJsonPath('data.items.0.medicine_id', null);
    }

    public function test_medicine_catalog_only_lists_billable_medicines(): void
    {
        $this->actingAsReceptionist();

        $active = $this->medicine(['name' => 'Active medicine', 'price' => 10]);
        $inactive = $this->medicine(['is_active' => false]);
        $recalled = $this->medicine(['is_recalled' => true]);
        $zeroPrice = $this->medicine(['price' => 0]);

        $response = $this->getJson('/api/v1/invoices/medicines')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactive->id));
        $this->assertFalse($ids->contains($recalled->id));
        $this->assertFalse($ids->contains($zeroPrice->id));
    }
}
