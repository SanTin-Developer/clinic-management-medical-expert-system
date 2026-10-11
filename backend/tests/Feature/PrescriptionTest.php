<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrescriptionTest extends TestCase
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
            'prescription_number' => 'RX-TEST-'.fake()->unique()->numerify('####'),
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'pending',
            'issued_at' => now(),
        ]);
    }

    public function test_doctor_can_create_prescription(): void
    {
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $this->postJson('/api/v1/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 3, 'dosage' => '1 tab'],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('prescription_items', [
            'medicine_id' => $medicine->id,
            'quantity' => 3,
        ]);
    }

    public function test_nurse_cannot_create_prescription(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $this->postJson('/api/v1/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_dispense_decrements_inventory_fifo(): void
    {
        $doctor = $this->userWithRole('Doctor');
        Sanctum::actingAs($doctor);

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $early = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 2,
            'expiration_date' => now()->addDays(10),
        ]);
        $late = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 10,
            'expiration_date' => now()->addDays(60),
        ]);

        $prescription = $this->prescriptionFor($doctor, $patient);
        $prescription->items()->create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $this->postJson("/api/v1/prescriptions/{$prescription->id}/dispense")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispensed');

        $this->assertSame(0, $early->fresh()->quantity);
        $this->assertSame(9, $late->fresh()->quantity);
        $this->assertSame(2, StockMovement::where('reference', $prescription->prescription_number)->count());
    }

    public function test_dispense_with_insufficient_stock_is_rejected(): void
    {
        $doctor = $this->userWithRole('Doctor');
        Sanctum::actingAs($doctor);

        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        $item = InventoryItem::create([
            'medicine_id' => $medicine->id,
            'quantity' => 2,
            'expiration_date' => now()->addDays(10),
        ]);

        $prescription = $this->prescriptionFor($doctor, $patient);
        $prescription->items()->create(['medicine_id' => $medicine->id, 'quantity' => 100]);

        $this->postJson("/api/v1/prescriptions/{$prescription->id}/dispense")
            ->assertStatus(422);

        $this->assertSame('pending', $prescription->fresh()->status);
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
