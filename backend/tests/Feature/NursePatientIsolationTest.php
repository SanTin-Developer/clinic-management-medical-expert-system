<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\Room;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NursePatientIsolationTest extends TestCase
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

    public function test_nurse_can_only_see_assigned_patients(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $otherPatient = Patient::factory()->create();
        $assignedPatient = Patient::factory()->create();

        PatientAssignment::create([
            "nurse_id" => $nurse->id,
            "patient_id" => $assignedPatient->id,
            "status" => "active",
            "starts_at" => now(),
        ]);

        Sanctum::actingAs($nurse);

        $response = $this->getJson("/api/v1/patients");
        $response->assertOk();
        $ids = collect($response->json("data"))->pluck("id");
        $this->assertTrue($ids->contains($assignedPatient->id));
        $this->assertFalse($ids->contains($otherPatient->id));
    }

    public function test_nurse_cannot_view_unassigned_patient(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $other = Patient::factory()->create();

        Sanctum::actingAs($nurse);
        $this->getJson("/api/v1/patients/".$other->id)->assertForbidden();
    }

    public function test_nurse_cannot_see_unassigned_vitals(): void
    {
        $nurse = $this->userWithRole("Nurse");
        Patient::factory()->create();
        Sanctum::actingAs($nurse);
        $this->getJson("/api/v1/vital-signs")->assertOk();
    }

    public function test_nurse_with_ward_assignment_can_access_admitted_patient(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $patient = Patient::factory()->create();

        $ward = Ward::factory()->create();
        $room = Room::factory()->create(["ward_id" => $ward->id]);
        $bed = Bed::factory()->create(["room_id" => $room->id, "status" => "available"]);

        Admission::create([
            "admission_number" => "ADM-TEST-1",
            "patient_id" => $patient->id,
            "ward_id" => $ward->id,
            "bed_id" => $bed->id,
            "status" => Admission::STATUS_ADMITTED,
            "admitted_at" => now(),
        ]);

        PatientAssignment::create([
            "nurse_id" => $nurse->id,
            "ward_id" => $ward->id,
            "status" => "active",
            "starts_at" => now(),
        ]);

        Sanctum::actingAs($nurse);
        $this->getJson("/api/v1/patients/".$patient->id)->assertOk();
    }

    public function test_non_restricted_roles_see_all(): void
    {
        $doctor = $this->userWithRole("Doctor");
        $patient = Patient::factory()->create();

        Sanctum::actingAs($doctor);
        $this->getJson("/api/v1/patients")->assertOk();
        $this->getJson("/api/v1/patients/".$patient->id)->assertOk();
    }
}

