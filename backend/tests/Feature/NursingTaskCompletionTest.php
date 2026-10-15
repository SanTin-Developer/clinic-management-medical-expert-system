<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class NursingTaskCompletionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingNurseFor(Patient $patient): User
    {
        $nurse = $this->userWithRole('Nurse');

        PatientAssignment::create([
            'nurse_id' => $nurse->id,
            'patient_id' => $patient->id,
            'status' => PatientAssignment::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        Sanctum::actingAs($nurse);

        return $nurse;
    }

    public function test_vital_signs_task_cannot_be_completed_without_measurements(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_VITAL_SIGNS,
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete")
            ->assertStatus(422);

        $this->assertSame(NursingTask::STATUS_PENDING, $task->fresh()->status);
        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_completing_vital_signs_task_records_linked_vitals_and_completes_task(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_VITAL_SIGNS,
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete", [
            'temperature' => 38.1,
            'heart_rate' => 96,
            'symptoms' => 'Fever, chills',
        ])->assertOk()
            ->assertJsonPath('data.status', NursingTask::STATUS_COMPLETED)
            ->assertJsonPath('data.outcome', NursingTask::OUTCOME_COMPLETED);

        $this->assertDatabaseHas('vital_signs', [
            'nursing_task_id' => $task->id,
            'patient_id' => $patient->id,
            'nurse_id' => $nurse->id,
        ]);

        $vitalSign = VitalSign::query()->where('nursing_task_id', $task->id)->firstOrFail();
        $this->assertEqualsWithDelta(38.1, (float) $vitalSign->temperature, 0.001);
        $this->assertSame(96, (int) $vitalSign->heart_rate);
    }

    public function test_medication_task_completion_administers_linked_dose(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_MEDICATION,
        ]);

        $record = MedicationAdministration::factory()->create([
            'patient_id' => $patient->id,
            'nursing_task_id' => $task->id,
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'scheduled_at' => now(),
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete", [
            'outcome' => MedicationAdministration::STATUS_ADMINISTERED,
            'dose' => '1 tablet',
            'route' => 'oral',
        ])->assertOk()->assertJsonPath('data.status', NursingTask::STATUS_COMPLETED);

        $fresh = $record->fresh();

        $this->assertSame(MedicationAdministration::STATUS_ADMINISTERED, $fresh->status);
        $this->assertSame($task->id, $fresh->nursing_task_id);
        $this->assertSame($nurse->id, $fresh->administered_by);
    }

    public function test_medication_task_stays_pending_when_administration_fails(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_MEDICATION,
        ]);

        $record = MedicationAdministration::factory()->create([
            'patient_id' => $patient->id,
            'nursing_task_id' => $task->id,
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'scheduled_at' => now(),
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete")
            ->assertStatus(422);

        $this->assertSame(NursingTask::STATUS_PENDING, $task->fresh()->status);
        $this->assertSame(MedicationAdministration::STATUS_SCHEDULED, $record->fresh()->status);
    }

    public function test_incomplete_outcome_requires_a_reason(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_OBSERVATION,
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete", [
            'outcome' => NursingTask::OUTCOME_PARTIALLY_COMPLETED,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete", [
            'outcome' => NursingTask::OUTCOME_PARTIALLY_COMPLETED,
            'reason' => 'Patient asleep during observation round.',
        ])->assertOk()
            ->assertJsonPath('data.outcome', NursingTask::OUTCOME_PARTIALLY_COMPLETED)
            ->assertJsonPath('data.outcome_reason', 'Patient asleep during observation round.');
    }

    public function test_observation_task_defaults_to_completed_outcome(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_OBSERVATION,
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.outcome', NursingTask::OUTCOME_COMPLETED);

        $this->assertSame(NursingTask::STATUS_COMPLETED, $task->fresh()->status);
    }

    public function test_medication_task_detail_exposes_the_scheduled_dose(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_MEDICATION,
        ]);

        $record = MedicationAdministration::factory()->create([
            'patient_id' => $patient->id,
            'nursing_task_id' => $task->id,
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'scheduled_at' => now(),
        ]);

        $this->getJson("/api/v1/nursing-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.scheduled_dose.id', $record->id)
            ->assertJsonPath('data.scheduled_dose.status', MedicationAdministration::STATUS_SCHEDULED);
    }

    public function test_medication_task_detail_reports_an_already_recorded_dose(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_MEDICATION,
        ]);

        MedicationAdministration::factory()->create([
            'patient_id' => $patient->id,
            'nursing_task_id' => $task->id,
            'status' => MedicationAdministration::STATUS_ADMINISTERED,
            'scheduled_at' => now(),
        ]);

        $this->getJson("/api/v1/nursing-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.scheduled_dose.status', MedicationAdministration::STATUS_ADMINISTERED);
    }

    public function test_a_task_cannot_be_completed_twice(): void
    {
        $patient = Patient::factory()->create();
        $nurse = $this->actingNurseFor($patient);

        $task = NursingTask::factory()->create([
            'patient_id' => $patient->id,
            'assigned_nurse_id' => $nurse->id,
            'task_type' => NursingTask::TYPE_OBSERVATION,
        ]);

        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete")->assertOk();
        $this->postJson("/api/v1/nursing-tasks/{$task->id}/complete")->assertStatus(422);
    }
}
