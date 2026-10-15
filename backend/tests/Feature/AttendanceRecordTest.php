<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_can_clock_in_and_out_for_themselves(): void
    {
        $nurse = $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/attendance/clock-in')
            ->assertOk()
            ->assertJsonPath('data.user_id', $nurse->id)
            ->assertJsonPath('data.status', 'present');

        $this->postJson('/api/v1/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('data.user_id', $nurse->id);

        $this->assertDatabaseHas('attendance_records', ['user_id' => $nurse->id]);
        $this->assertTrue(
            AttendanceRecord::where('user_id', $nurse->id)
                ->whereDate('work_date', now()->toDateString())
                ->exists()
        );
    }

    public function test_staff_can_view_their_today_record(): void
    {
        $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('/api/v1/attendance/clock-in')->assertOk();

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', 'present');
    }

    public function test_staff_cannot_clock_in_twice(): void
    {
        $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/attendance/clock-in')->assertOk();
        $this->postJson('/api/v1/attendance/clock-in')->assertStatus(422);
    }

    public function test_staff_cannot_clock_out_without_clocking_in(): void
    {
        $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/attendance/clock-out')->assertStatus(422);
    }

    public function test_staff_only_see_their_own_attendance(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        AttendanceRecord::factory()->create(['user_id' => $nurse->id]);
        AttendanceRecord::factory()->create(['user_id' => $this->userWithRole('Doctor')->id]);

        $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user_id', $nurse->id);
    }

    public function test_manager_sees_all_records_and_can_filter_by_status(): void
    {
        $this->actingAsRole('Head Nurse');
        AttendanceRecord::factory()->create(['user_id' => $this->userWithRole('Nurse')->id]);
        AttendanceRecord::factory()->create([
            'user_id' => $this->userWithRole('Doctor')->id,
            'status' => 'late',
        ]);

        $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('stats.late', 1);

        $this->getJson('/api/v1/attendance?status=late')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'late');
    }

    public function test_manager_can_create_a_manual_record(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');

        $this->postJson('/api/v1/attendance', [
            'user_id' => $nurse->id,
            'work_date' => '2030-05-01',
            'clock_in' => '2030-05-01 08:05:00',
            'clock_out' => '2030-05-01 16:10:00',
            'status' => 'present',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $nurse->id);
    }

    public function test_manager_cannot_create_a_duplicate_record(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        AttendanceRecord::factory()->create([
            'user_id' => $nurse->id,
            'work_date' => '2030-05-02',
        ]);

        $this->postJson('/api/v1/attendance', [
            'user_id' => $nurse->id,
            'work_date' => '2030-05-02',
        ])->assertStatus(422);
    }

    public function test_manager_can_approve_a_record(): void
    {
        $manager = $this->actingAsRole('Head Nurse');
        $record = AttendanceRecord::factory()->create([
            'user_id' => $this->userWithRole('Nurse')->id,
        ]);

        $this->postJson("/api/v1/attendance/{$record->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approved_by', $manager->id);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'approved_by' => $manager->id,
        ]);
    }

    public function test_staff_cannot_approve_records(): void
    {
        $this->actingAsRole('Nurse');
        $record = AttendanceRecord::factory()->create([
            'user_id' => $this->userWithRole('Doctor')->id,
        ]);

        $this->postJson("/api/v1/attendance/{$record->id}/approve")->assertForbidden();
    }

    public function test_manager_can_update_and_delete_a_record(): void
    {
        $this->actingAsRole('Head Nurse');
        $record = AttendanceRecord::factory()->create([
            'user_id' => $this->userWithRole('Nurse')->id,
        ]);

        $this->putJson("/api/v1/attendance/{$record->id}", ['status' => 'absent'])
            ->assertOk()
            ->assertJsonPath('data.status', 'absent');

        $this->deleteJson("/api/v1/attendance/{$record->id}")->assertOk();
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    public function test_patient_without_permission_cannot_list_attendance(): void
    {
        Sanctum::actingAs($this->userWithRole('Patient'));

        $this->getJson('/api/v1/attendance')->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_attendance(): void
    {
        $this->getJson('/api/v1/attendance')->assertUnauthorized();
    }
}
