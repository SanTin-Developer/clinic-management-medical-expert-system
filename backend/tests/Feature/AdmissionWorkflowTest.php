<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdmissionWorkflowTest extends TestCase
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

    public function test_admit_occupies_bed_and_creates_admission(): void
    {
        $admin = $this->userWithRole("Admin");
        $patient = Patient::factory()->create();
        $ward = Ward::factory()->create();
        $room = Room::factory()->create(["ward_id" => $ward->id]);
        $bed = Bed::factory()->create(["room_id" => $room->id, "status" => "available"]);
        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/v1/admissions", [
            "patient_id" => $patient->id,
            "bed_id" => $bed->id,
            "reason" => "Observation",
        ]);
        $response->assertCreated();
        $this->assertDatabaseHas("admissions", [
            "patient_id" => $patient->id,
            "status" => Admission::STATUS_ADMITTED,
        ]);
        $this->assertSame("occupied", $bed->fresh()->status);
    }

    public function test_discharge_frees_bed(): void
    {
        $admin = $this->userWithRole("Admin");
        $patient = Patient::factory()->create();
        $ward = Ward::factory()->create();
        $room = Room::factory()->create(["ward_id" => $ward->id]);
        $bed = Bed::factory()->create(["room_id" => $room->id, "status" => "available"]);
        Sanctum::actingAs($admin);
        $admit = $this->postJson("/api/v1/admissions", [
            "patient_id" => $patient->id,
            "bed_id" => $bed->id,
        ])->assertCreated();
        $admissionId = $admit->json("data.id");
        $this->postJson("/api/v1/admissions/".$admissionId."/discharge")->assertOk();
        $this->assertDatabaseHas("admissions", [
            "id" => $admissionId,
            "status" => Admission::STATUS_DISCHARGED,
        ]);
        $this->assertSame("available", $bed->fresh()->status);
    }
}

