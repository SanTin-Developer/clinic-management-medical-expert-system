<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MedicationAdministrationTest extends TestCase
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

    public function test_nurse_can_schedule_mar_for_assigned_patient(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        PatientAssignment::create([
            "nurse_id" => $nurse->id,
            "patient_id" => $patient->id,
            "status" => "active",
            "starts_at" => now(),
        ]);

        Sanctum::actingAs($nurse);

        $response = $this->postJson("/api/v1/medication-administrations", [
            "patient_id" => $patient->id,
            "medicine_id" => $medicine->id,
            "dose" => "1 tab",
            "route" => "oral",
            "scheduled_at" => now()->addHours(2)->toIso8601String(),
        ]);

        $response->assertCreated();
        $marId = $response->json("data.id");

        $this->postJson("/api/v1/medication-administrations/".$marId."/administer")->assertOk();
        $this->assertSame(MedicationAdministration::STATUS_ADMINISTERED, MedicationAdministration::find($marId)->status);
    }

    public function test_mar_administer_does_not_change_inventory(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $pharmacist = $this->userWithRole("Pharmacist");
        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create();

        PatientAssignment::create([
            "nurse_id" => $nurse->id,
            "patient_id" => $patient->id,
            "status" => "active",
            "starts_at" => now(),
        ]);

        Sanctum::actingAs($nurse);

        $mar = $this->postJson("/api/v1/medication-administrations", [
            "patient_id" => $patient->id,
            "medicine_id" => $medicine->id,
            "dose" => "1 tab",
            "route" => "oral",
            "scheduled_at" => now()->addHours(2)->toIso8601String(),
        ])->assertCreated();

        $marId = $mar->json("data.id");
        $initialStock = \App\Models\InventoryItem::query()->count();

        $this->postJson("/api/v1/medication-administrations/".$marId."/administer")->assertOk();

        $this->assertSame($initialStock, \App\Models\InventoryItem::query()->count());
    }
}

