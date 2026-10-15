<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_pharmacist_can_create_and_list_medicines(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $this->postJson('/api/v1/medicines', [
            'name' => 'Paracetamol',
            'price' => 5.5,
            'reorder_level' => 10,
        ])->assertCreated()->assertJsonPath('data.name', 'Paracetamol');

        $this->getJson('/api/v1/medicines')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_doctor_cannot_create_medicine(): void
    {
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->postJson('/api/v1/medicines', ['name' => 'Paracetamol'])
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_medicines(): void
    {
        $this->getJson('/api/v1/medicines')->assertUnauthorized();
    }

    public function test_inventory_low_stock_filter(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $low = Medicine::factory()->create(['reorder_level' => 10]);
        $healthy = Medicine::factory()->create(['reorder_level' => 10]);

        InventoryItem::create(['medicine_id' => $low->id, 'quantity' => 5]);
        InventoryItem::create(['medicine_id' => $healthy->id, 'quantity' => 50]);

        $response = $this->getJson('/api/v1/inventory-items?low_stock=1')->assertOk();

        $medicineIds = collect($response->json('data'))->pluck('medicine_id');

        $this->assertTrue($medicineIds->contains($low->id));
        $this->assertFalse($medicineIds->contains($healthy->id));
    }

    public function test_low_stock_filter_uses_total_stock_across_batches(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $medicine = Medicine::factory()->create(['reorder_level' => 10]);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 6]);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 6]);

        $this->getJson('/api/v1/inventory-items?low_stock=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inventory_lists_batch_expiry_information_and_can_filter_expired_stock(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $medicine = Medicine::factory()->create();
        $expired = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'EXPIRED-001',
            'quantity' => 4,
            'expiration_date' => now()->subDay()->toDateString(),
        ]);
        InventoryItem::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'CURRENT-001',
            'quantity' => 7,
            'expiration_date' => now()->addMonth()->toDateString(),
        ]);

        $this->getJson('/api/v1/inventory-items?expired=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $expired->id)
            ->assertJsonPath('data.0.batch_number', 'EXPIRED-001')
            ->assertJsonPath('data.0.expiration_date', now()->subDay()->startOfDay()->toJSON());
    }

    public function test_stock_in_increases_quantity(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $medicine = Medicine::factory()->create();
        $item = InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 5]);

        $this->postJson('/api/v1/stock-movements', [
            'medicine_id' => $medicine->id,
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 10,
        ])->assertCreated();

        $this->assertSame(15, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 10,
        ]);
    }

    public function test_stock_out_beyond_quantity_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $medicine = Medicine::factory()->create();
        $item = InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 2]);

        $this->postJson('/api/v1/stock-movements', [
            'medicine_id' => $medicine->id,
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 5,
        ])->assertStatus(422);

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_stock_adjustment_can_set_a_batch_to_zero(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 2]);

        $this->postJson('/api/v1/stock-movements', [
            'medicine_id' => $medicine->id,
            'inventory_item_id' => $item->id,
            'type' => 'adjustment',
            'quantity' => 0,
        ])->assertCreated();

        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_stock_movements_can_be_filtered_by_type_and_include_batch_and_actor(): void
    {
        $user = $this->userWithRole('Pharmacist');
        Sanctum::actingAs($user);
        $medicine = Medicine::factory()->create();
        $batch = InventoryItem::create(['medicine_id' => $medicine->id, 'batch_number' => 'LOT-001', 'quantity' => 5]);

        $this->postJson('/api/v1/stock-movements', [
            'medicine_id' => $medicine->id,
            'inventory_item_id' => $batch->id,
            'type' => 'in',
            'quantity' => 2,
            'reference' => 'DELIVERY-42',
        ])->assertCreated();

        $this->getJson('/api/v1/stock-movements?type=in')
            ->assertOk()
            ->assertJsonPath('data.0.inventory_item.batch_number', 'LOT-001')
            ->assertJsonPath('data.0.user.id', $user->id)
            ->assertJsonPath('data.0.reference', 'DELIVERY-42')
            ->assertJsonPath('stats.in', 1);
    }

    public function test_receiving_a_batch_creates_an_auditable_stock_receipt(): void
    {
        $user = $this->userWithRole('Pharmacist');
        Sanctum::actingAs($user);
        $medicine = Medicine::factory()->create();

        $this->postJson('/api/v1/inventory-items', [
            'medicine_id' => $medicine->id,
            'batch_number' => 'LOT-2026-001',
            'quantity' => 12,
            'reference' => 'DELIVERY-101',
        ])->assertCreated()->assertJsonPath('data.quantity', 12);

        $this->assertDatabaseHas('stock_movements', [
            'medicine_id' => $medicine->id,
            'user_id' => $user->id,
            'type' => 'in',
            'quantity' => 12,
            'reason' => 'Stock receipt',
            'reference' => 'DELIVERY-101',
        ]);
    }

    public function test_duplicate_batch_receipt_for_the_same_medicine_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $medicine = Medicine::factory()->create();
        InventoryItem::create(['medicine_id' => $medicine->id, 'batch_number' => 'LOT-2026-001', 'quantity' => 5]);

        $this->postJson('/api/v1/inventory-items', [
            'medicine_id' => $medicine->id,
            'batch_number' => 'LOT-2026-001',
            'quantity' => 12,
        ])->assertUnprocessable()->assertJsonValidationErrors('batch_number');

        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_inventory_quantity_cannot_be_changed_outside_stock_movement(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $item = InventoryItem::create(['medicine_id' => Medicine::factory()->create()->id, 'quantity' => 5]);

        $this->patchJson("/api/v1/inventory-items/{$item->id}", ['quantity' => 10])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Change stock quantity using an auditable stock movement.');

        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_inventory_batch_with_a_ledger_entry_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $item = InventoryItem::create(['medicine_id' => Medicine::factory()->create()->id, 'quantity' => 3]);
        $item->stockMovements()->create(['medicine_id' => $item->medicine_id, 'user_id' => null, 'type' => 'in', 'quantity' => 3]);

        $this->deleteJson("/api/v1/inventory-items/{$item->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A batch with stock movements cannot be deleted. Record an adjustment instead.');

        $this->assertModelExists($item);
    }

    public function test_pos_checkout_uses_server_price_and_records_invoice_and_stock_ledger(): void
    {
        $user = $this->userWithRole('Pharmacist');
        Sanctum::actingAs($user);
        $medicine = Medicine::factory()->create(['price' => 4.5]);
        $batch = InventoryItem::create(['medicine_id' => $medicine->id, 'batch_number' => 'POS-LOT', 'quantity' => 5, 'expiration_date' => now()->addMonth()]);

        $this->postJson('/api/v1/pharmacy/pos/checkout', [
            'idempotency_key' => 'b6dbf1c5-0aeb-49e4-97e3-2ea9140c1c1a',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2, 'unit_price' => 0.01]],
            'payment' => ['amount' => 9, 'method' => 'cash'],
        ])->assertCreated()->assertJsonPath('data.total', '9.00');

        $this->assertSame(3, $batch->fresh()->quantity);
        $this->assertDatabaseHas('invoice_items', ['description' => $medicine->name, 'unit_price' => 4.5, 'amount' => 9]);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $batch->id, 'type' => 'out', 'quantity' => 2]);
    }

    public function test_pos_product_search_only_marks_unexpired_stock_as_available(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $medicine = Medicine::factory()->create(['name' => 'POS Search Medicine']);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 7, 'expiration_date' => now()->subDay()]);

        $this->getJson('/api/v1/pharmacy/pos/products?search=Search')
            ->assertOk()
            ->assertJsonPath('data.0.id', $medicine->id)
            ->assertJsonPath('data.0.stock_status', 'unavailable')
            ->assertJsonPath('data.0.usable_stock', null);
    }

    public function test_pos_reuses_idempotency_key_without_deducting_stock_twice(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));
        $medicine = Medicine::factory()->create(['price' => 3]);
        $batch = InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 5, 'expiration_date' => now()->addDay()]);
        $payload = ['idempotency_key' => '8304d30f-0479-4e11-8e43-ff3a1f585f3a', 'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]], 'payment' => ['amount' => 3, 'amount_received' => 3, 'method' => 'cash']];

        $this->postJson('/api/v1/pharmacy/pos/checkout', $payload)->assertCreated();
        $this->postJson('/api/v1/pharmacy/pos/checkout', $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(4, $batch->fresh()->quantity);
        $this->assertDatabaseCount('pharmacy_sales', 1);
    }
}
