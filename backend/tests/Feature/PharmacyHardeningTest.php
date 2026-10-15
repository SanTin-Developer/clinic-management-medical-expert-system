<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Pharmacy\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PharmacyHardeningTest extends TestCase
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

    private function prescriptionFor(User $doctor, Patient $patient): Prescription
    {
        return Prescription::create([
            'prescription_number' => 'RX-HARD-'.fake()->unique()->numerify('####'),
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'pending',
            'issued_at' => now(),
        ]);
    }

    public function test_dispense_skips_expired_batches_and_uses_usable_stock(): void
    {
        $doctor = $this->userWithRole('Doctor');
        Sanctum::actingAs($doctor);

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $expired = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 100,
            'expiration_date' => now()->subDay(),
        ]);
        $usable = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 5,
            'expiration_date' => now()->addDays(40),
        ]);

        $prescription = $this->prescriptionFor($doctor, $patient);
        $prescription->items()->create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $this->postJson("/api/v1/prescriptions/{$prescription->id}/dispense")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispensed');

        $this->assertSame(100, $expired->fresh()->quantity, 'Expired stock must never be dispensed.');
        $this->assertSame(2, $usable->fresh()->quantity);
    }

    public function test_dispense_is_rejected_when_only_expired_stock_exists(): void
    {
        $doctor = $this->userWithRole('Doctor');
        Sanctum::actingAs($doctor);

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $expired = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 100,
            'expiration_date' => now()->subDay(),
        ]);

        $prescription = $this->prescriptionFor($doctor, $patient);
        $prescription->items()->create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $this->postJson("/api/v1/prescriptions/{$prescription->id}/dispense")
            ->assertStatus(422)
            ->assertJsonPath('message', "Only expired stock is available for {$medicine->name}.");

        $this->assertSame('pending', $prescription->fresh()->status);
        $this->assertSame(100, $expired->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inventory_service_allocates_fefo_across_batches(): void
    {
        $medicine = Medicine::factory()->create();
        $pharmacist = $this->userWithRole('Pharmacist');

        $soon = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 2,
            'expiration_date' => now()->addDays(5),
        ]);
        $later = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 10,
            'expiration_date' => now()->addDays(90),
        ]);

        DB::transaction(function () use ($medicine, $pharmacist) {
            app(InventoryService::class)->allocate(
                medicine: $medicine,
                quantity: 4,
                userId: $pharmacist->id,
                reason: 'Test allocation',
            );
        });

        $this->assertSame(0, $soon->fresh()->quantity);
        $this->assertSame(8, $later->fresh()->quantity);
        $this->assertSame(2, StockMovement::where('medicine_id', $medicine->id)->count());
    }

    public function test_pharmacist_can_view_pharmacy_dashboard(): void
    {
        Sanctum::actingAs($this->userWithRole('Pharmacist'));

        $this->getJson('/api/v1/pharmacy/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'inventory' => ['active_medicines', 'low_stock_count', 'expired_count', 'stock_value'],
                    'prescriptions' => ['pending', 'dispensed_today'],
                    'movements_today',
                ],
            ]);
    }

    public function test_patient_cannot_view_pharmacy_dashboard(): void
    {
        Sanctum::actingAs($this->userWithRole('Patient'));

        $this->getJson('/api/v1/pharmacy/dashboard')->assertForbidden();
    }

    public function test_full_dispense_records_actor_and_timestamp(): void
    {
        $doctor = $this->userWithRole('Doctor');
        $pharmacist = $this->userWithRole('Pharmacist');
        Sanctum::actingAs($pharmacist);

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 10,
            'expiration_date' => now()->addDays(30),
        ]);

        $prescription = $this->prescriptionFor($doctor, $patient);
        $prescription->items()->create(['medicine_id' => $medicine->id, 'quantity' => 2]);

        $this->postJson("/api/v1/prescriptions/{$prescription->id}/dispense")->assertOk();

        $fresh = $prescription->fresh();
        $this->assertSame('dispensed', $fresh->status);
        $this->assertSame($pharmacist->id, $fresh->dispensed_by);
        $this->assertNotNull($fresh->dispensed_at);
    }
}
