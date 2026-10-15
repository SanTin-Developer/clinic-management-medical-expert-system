<?php

namespace Tests\Feature;

use App\Models\Shift;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_manager_can_schedule_a_shift(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'type' => 'day',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $nurse->id)
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_staff_cannot_schedule_shifts(): void
    {
        $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/shifts', [
            'user_id' => $doctor->id,
            'shift_date' => '2030-06-02',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertForbidden();
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-03',
            'start_time' => '16:00',
            'end_time' => '08:00',
        ])->assertStatus(422);
    }

    public function test_staff_only_see_their_own_shifts(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        Shift::factory()->create(['user_id' => $nurse->id]);
        Shift::factory()->create(['user_id' => $this->userWithRole('Doctor')->id]);

        $this->getJson('/api/v1/shifts')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user_id', $nurse->id);
    }

    public function test_manager_sees_all_shifts_and_can_filter_by_type(): void
    {
        $this->actingAsRole('Head Nurse');
        Shift::factory()->create(['user_id' => $this->userWithRole('Nurse')->id, 'type' => 'day']);
        Shift::factory()->create(['user_id' => $this->userWithRole('Doctor')->id, 'type' => 'night']);

        $this->getJson('/api/v1/shifts')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/v1/shifts?type=night')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.type', 'night');
    }

    public function test_manager_can_update_and_delete_a_shift(): void
    {
        $this->actingAsRole('Head Nurse');
        $shift = Shift::factory()->create(['user_id' => $this->userWithRole('Nurse')->id]);

        $this->putJson("/api/v1/shifts/{$shift->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->deleteJson("/api/v1/shifts/{$shift->id}")->assertOk();
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_patient_without_permission_cannot_list_shifts(): void
    {
        Sanctum::actingAs($this->userWithRole('Patient'));

        $this->getJson('/api/v1/shifts')->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_shifts(): void
    {
        $this->getJson('/api/v1/shifts')->assertUnauthorized();
    }
}
