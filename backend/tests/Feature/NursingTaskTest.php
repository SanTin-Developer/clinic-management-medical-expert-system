<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\NursingTask;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NursingTaskTest extends TestCase
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

    public function test_nurse_can_create_and_complete_task_for_assigned_patient(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $patient = Patient::factory()->create();

        PatientAssignment::create([
            "nurse_id" => $nurse->id,
            "patient_id" => $patient->id,
            "status" => "active",
            "starts_at" => now(),
        ]);

        Sanctum::actingAs($nurse);

        $response = $this->postJson("/api/v1/nursing-tasks", [
            "patient_id" => $patient->id,
            "task_type" => "observation",
            "title" => "Monitor vitals",
            "scheduled_at" => now()->addHour()->toIso8601String(),
        ]);

        $response->assertCreated();
        $taskId = $response->json("data.id");

        $complete = $this->postJson("/api/v1/nursing-tasks/".$taskId."/complete");
        $complete->assertOk();
        $this->assertSame("completed", NursingTask::find($taskId)->status);
    }

    public function test_nurse_cannot_create_task_for_unassigned_patient(): void
    {
        $nurse = $this->userWithRole("Nurse");
        $patient = Patient::factory()->create();

        Sanctum::actingAs($nurse);

        $this->postJson("/api/v1/nursing-tasks", [
            "patient_id" => $patient->id,
            "task_type" => "observation",
            "title" => "Monitor vitals",
            "scheduled_at" => now()->addHour()->toIso8601String(),
        ])->assertForbidden();
    }
}

