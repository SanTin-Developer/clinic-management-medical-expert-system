<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\ShiftTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ShiftSchedulingTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config(['attendance.timezone' => 'Asia/Phnom_Penh']);
    }

    public function test_manager_can_schedule_a_shift_from_a_template(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        $template = $this->template();

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-01',
            'shift_template_id' => $template->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.shift_template_id', $template->id)
            ->assertJsonPath('data.start_time', '07:30')
            ->assertJsonPath('data.end_time', '14:30')
            ->assertJsonPath('data.crosses_midnight', false);
    }

    public function test_an_overnight_shift_can_be_scheduled(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        $template = $this->template(['type' => 'night', 'start_time' => '21:30', 'end_time' => '07:00', 'crosses_midnight' => true]);

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-01',
            'shift_template_id' => $template->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.crosses_midnight', true)
            ->assertJsonPath('data.end_time', '07:00');
    }

    public function test_a_manual_shift_needs_a_valid_time_range(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-02',
            'start_time' => '16:00',
            'end_time' => '08:00',
        ])->assertStatus(422);

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-03',
            'start_time' => '21:30',
            'end_time' => '07:00',
            'crosses_midnight' => true,
        ])->assertCreated();
    }

    public function test_a_duplicate_shift_on_the_same_day_is_rejected(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        Shift::factory()->create(['user_id' => $nurse->id, 'shift_date' => '2030-06-04', 'status' => 'scheduled']);

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-04',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertStatus(422);
    }

    public function test_an_overnight_shift_cannot_overlap_the_next_day(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        $night = $this->template(['type' => 'night', 'start_time' => '21:30', 'end_time' => '07:00', 'crosses_midnight' => true]);

        Shift::factory()->create([
            'user_id' => $nurse->id,
            'shift_template_id' => $night->id,
            'shift_date' => '2030-06-05',
            'start_time' => '21:30',
            'end_time' => '07:00',
            'crosses_midnight' => true,
            'status' => 'scheduled',
        ]);

        $this->postJson('/api/v1/shifts', [
            'user_id' => $nurse->id,
            'shift_date' => '2030-06-06',
            'start_time' => '06:00',
            'end_time' => '08:00',
        ])->assertStatus(422);
    }

    public function test_uncovered_endpoint_lists_unassigned_template_dates(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        $template = $this->template();

        $this->getJson('/api/v1/shifts/uncovered?from=2030-06-10&to=2030-06-10&shift_template_id='.$template->id)
            ->assertOk()
            ->assertJsonPath('data.0.date', '2030-06-10')
            ->assertJsonPath('data.0.shift_template_id', $template->id);

        Shift::factory()->create([
            'user_id' => $nurse->id,
            'shift_template_id' => $template->id,
            'shift_date' => '2030-06-10',
            'status' => 'scheduled',
        ]);

        $this->getJson('/api/v1/shifts/uncovered?from=2030-06-10&to=2030-06-10&shift_template_id='.$template->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_board_endpoint_returns_the_requested_range(): void
    {
        $this->actingAsRole('Head Nurse');
        Shift::factory()->create(['user_id' => $this->userWithRole('Nurse')->id, 'shift_date' => '2030-06-15']);
        Shift::factory()->create(['user_id' => $this->userWithRole('Nurse')->id, 'shift_date' => '2030-06-20']);

        $this->getJson('/api/v1/shifts/board?from=2030-06-14&to=2030-06-16')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shift_date', '2030-06-15T00:00:00.000000Z');
    }

    public function test_shifts_can_be_published_individually_and_in_bulk(): void
    {
        $this->actingAsRole('Head Nurse');
        $nurse = $this->userWithRole('Nurse');
        $first = Shift::factory()->create(['user_id' => $nurse->id, 'shift_date' => '2030-06-25', 'published_at' => null]);
        $second = Shift::factory()->create(['user_id' => $nurse->id, 'shift_date' => '2030-06-26', 'published_at' => null]);
        $third = Shift::factory()->create(['user_id' => $nurse->id, 'shift_date' => '2030-06-27', 'published_at' => null]);

        $this->postJson("/api/v1/shifts/{$first->id}/publish")->assertOk();
        $this->assertNotNull($first->fresh()->published_at);

        $this->postJson('/api/v1/shifts/publish', ['ids' => [$second->id, $third->id]])
            ->assertOk()
            ->assertJsonPath('data.published', 2);
        $this->assertNotNull($second->fresh()->published_at);
        $this->assertNotNull($third->fresh()->published_at);
    }

    private function template(array $overrides = []): ShiftTemplate
    {
        return ShiftTemplate::factory()->create(array_merge([
            'name' => 'Template '.Str::random(6),
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
            'crosses_midnight' => false,
            'clock_out_opens_at' => '14:30',
            'clock_out_closes_at' => '15:00',
            'is_active' => true,
        ], $overrides));
    }
}
