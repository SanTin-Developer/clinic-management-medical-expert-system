<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_can_submit_a_leave_request_for_themselves(): void
    {
        $nurse = $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/leave-requests', [
            'type' => 'annual',
            'start_date' => '2030-03-01',
            'end_date' => '2030-03-03',
            'reason' => 'Family trip',
        ])
            ->assertCreated()
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.user_id', $nurse->id);
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/leave-requests', [
            'type' => 'annual',
            'start_date' => '2030-03-05',
            'end_date' => '2030-03-01',
        ])->assertStatus(422);
    }

    public function test_staff_only_see_their_own_requests(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        LeaveRequest::factory()->create(['user_id' => $nurse->id]);
        LeaveRequest::factory()->create(['user_id' => $this->userWithRole('Doctor')->id]);

        $this->getJson('/api/v1/leave-requests')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_manager_sees_all_requests_and_can_filter_by_status(): void
    {
        $this->actingAsRole('Head Nurse');
        LeaveRequest::factory()->create(['user_id' => $this->userWithRole('Nurse')->id]);
        LeaveRequest::factory()->approved()->create(['user_id' => $this->userWithRole('Doctor')->id]);

        $this->getJson('/api/v1/leave-requests')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('stats.pending', 1)
            ->assertJsonPath('stats.approved', 1);

        $this->getJson('/api/v1/leave-requests?status=approved')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_manager_can_approve_a_request(): void
    {
        $manager = $this->actingAsRole('Head Nurse');
        $leave = LeaveRequest::factory()->create(['user_id' => $this->userWithRole('Nurse')->id]);

        $this->postJson("/api/v1/leave-requests/{$leave->id}/approve", [
            'review_notes' => 'Approved, enjoy.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $manager->id);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
        ]);
    }

    public function test_manager_can_reject_a_request(): void
    {
        $this->actingAsRole('Head Nurse');
        $leave = LeaveRequest::factory()->create(['user_id' => $this->userWithRole('Nurse')->id]);

        $this->postJson("/api/v1/leave-requests/{$leave->id}/reject", [
            'review_notes' => 'Coverage gap.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_staff_cannot_approve_requests(): void
    {
        $this->actingAsRole('Nurse');
        $leave = LeaveRequest::factory()->create(['user_id' => $this->userWithRole('Doctor')->id]);

        $this->postJson("/api/v1/leave-requests/{$leave->id}/approve")->assertForbidden();
    }

    public function test_staff_can_cancel_their_own_pending_request(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $leave = LeaveRequest::factory()->create(['user_id' => $nurse->id]);

        $this->postJson("/api/v1/leave-requests/{$leave->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_updating_dates_recalculates_days(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $leave = LeaveRequest::factory()->create([
            'user_id' => $nurse->id,
            'start_date' => '2030-04-01',
            'end_date' => '2030-04-01',
            'days' => 1,
        ]);

        $this->putJson("/api/v1/leave-requests/{$leave->id}", [
            'end_date' => '2030-04-03',
        ])
            ->assertOk()
            ->assertJsonPath('data.days', 3);
    }

    public function test_patient_without_permission_cannot_list_leave_requests(): void
    {
        Sanctum::actingAs($this->userWithRole('Patient'));

        $this->getJson('/api/v1/leave-requests')->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_leave_requests(): void
    {
        $this->getJson('/api/v1/leave-requests')->assertUnauthorized();
    }
}
