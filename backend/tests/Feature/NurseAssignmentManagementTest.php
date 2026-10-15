<?php

namespace Tests\Feature;

use App\Models\AssignmentAuditLog;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\Ward;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class NurseAssignmentManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assignment(array $overrides = []): PatientAssignment
    {
        return PatientAssignment::create(array_merge([
            'nurse_id' => $this->userWithRole('Nurse')->id,
            'patient_id' => Patient::factory()->create()->id,
            'status' => PatientAssignment::STATUS_ACTIVE,
            'shift' => 'Morning',
            'starts_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_index_filters_by_shift_and_scope_type(): void
    {
        $this->actingAsRole('Admin');

        $patientAssignment = $this->assignment(['shift' => 'Morning']);
        $wardAssignment = $this->assignment([
            'patient_id' => null,
            'ward_id' => Ward::factory()->create()->id,
            'shift' => 'Night',
        ]);

        $this->getJson('/api/v1/assignments?shift=Night')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wardAssignment->id);

        $this->getJson('/api/v1/assignments?type=patient')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $patientAssignment->id);

        $this->getJson('/api/v1/assignments?type=ward')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wardAssignment->id);
    }

    public function test_index_reports_active_and_upcoming_stats(): void
    {
        $this->actingAsRole('Admin');

        $this->assignment(['starts_at' => now()->subHour()]);
        $this->assignment(['starts_at' => now()->addDay()]);
        $this->assignment([
            'status' => PatientAssignment::STATUS_ENDED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.upcoming', 1)
            ->assertJsonPath('stats.ended', 1);
    }

    public function test_assignment_details_can_be_updated_in_place(): void
    {
        $this->actingAsRole('Admin');

        $nurse = $this->userWithRole('Nurse');
        $assignment = $this->assignment([
            'nurse_id' => $nurse->id,
            'shift' => 'Morning',
        ]);

        $this->putJson("/api/v1/assignments/{$assignment->id}", [
            'nurse_id' => $nurse->id,
            'patient_id' => $assignment->patient_id,
            'shift' => 'Evening',
            'notes' => 'Cover evening round.',
            'reason' => 'Roster change',
        ])->assertOk()->assertJsonPath('data.shift', 'Evening');

        $this->assertSame(1, PatientAssignment::query()->count());
        $this->assertSame(PatientAssignment::STATUS_ACTIVE, $assignment->fresh()->status);
        $this->assertDatabaseHas('assignment_audit_logs', [
            'assignment_id' => $assignment->id,
            'action' => AssignmentAuditLog::ACTION_UPDATED,
        ]);
    }

    public function test_reassigning_to_another_nurse_ends_old_and_creates_new_assignment(): void
    {
        $this->actingAsRole('Admin');

        $originalNurse = $this->userWithRole('Nurse');
        $newNurse = $this->userWithRole('Nurse');
        $assignment = $this->assignment(['nurse_id' => $originalNurse->id]);

        $response = $this->putJson("/api/v1/assignments/{$assignment->id}", [
            'nurse_id' => $newNurse->id,
            'patient_id' => $assignment->patient_id,
            'shift' => 'Morning',
            'reason' => 'Handover',
        ])->assertOk();

        $newId = $response->json('data.id');

        $this->assertNotSame($assignment->id, $newId);
        $this->assertSame($newNurse->id, $response->json('data.nurse_id'));

        $this->assertSame(PatientAssignment::STATUS_ENDED, $assignment->fresh()->status);

        $this->assertDatabaseHas('patient_assignments', [
            'id' => $newId,
            'nurse_id' => $newNurse->id,
            'status' => PatientAssignment::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('assignment_audit_logs', [
            'assignment_id' => $assignment->id,
            'action' => AssignmentAuditLog::ACTION_REASSIGNED,
        ]);

        $this->assertDatabaseHas('assignment_audit_logs', [
            'assignment_id' => $newId,
            'action' => AssignmentAuditLog::ACTION_CREATED,
        ]);
    }

    public function test_ending_an_assignment_writes_an_audit_entry(): void
    {
        $this->actingAsRole('Admin');

        $assignment = $this->assignment();

        $this->postJson("/api/v1/assignments/{$assignment->id}/end", [
            'ended_reason' => 'Shift finished',
        ])->assertOk();

        $this->assertSame(PatientAssignment::STATUS_ENDED, $assignment->fresh()->status);
        $this->assertDatabaseHas('assignment_audit_logs', [
            'assignment_id' => $assignment->id,
            'action' => AssignmentAuditLog::ACTION_ENDED,
        ]);
    }

    public function test_nurse_without_manage_permission_cannot_update_assignments(): void
    {
        $this->actingAsRole('Nurse');

        $assignment = $this->assignment();

        $this->putJson("/api/v1/assignments/{$assignment->id}", [
            'nurse_id' => $assignment->nurse_id,
            'patient_id' => $assignment->patient_id,
        ])->assertForbidden();
    }
}
