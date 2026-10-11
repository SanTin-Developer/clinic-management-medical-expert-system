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
}
