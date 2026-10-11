<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_admin_can_view_summary(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->getJson('/api/v1/reports/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'patients' => ['total', 'new_this_month'],
                    'appointments' => ['total', 'by_status'],
                    'billing' => ['invoices_total', 'revenue_total', 'outstanding_balance'],
                    'inventory' => ['medicines_total', 'low_stock'],
                ],
            ]);
    }

    public function test_receptionist_cannot_view_reports(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $this->getJson('/api/v1/reports/summary')->assertForbidden();
    }

    public function test_revenue_report_reflects_recorded_payments(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $patient = Patient::factory()->create();

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 80]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 80,
            'method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/v1/reports/revenue')
            ->assertOk()
            ->assertJsonPath('data.total', 80)
            ->assertJsonPath('data.by_method.cash', 80)
            ->assertJsonPath('data.outstanding_balance', 0);
    }

    public function test_inventory_report_lists_low_stock(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $medicine = Medicine::factory()->create(['reorder_level' => 10]);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $this->getJson('/api/v1/reports/inventory')
            ->assertOk()
            ->assertJsonPath('data.low_stock.0.id', $medicine->id)
            ->assertJsonPath('data.low_stock.0.stock', 3);
    }
}
