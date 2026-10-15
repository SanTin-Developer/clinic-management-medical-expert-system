<?php

namespace Tests\Feature;

use App\Models\ShiftTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ShiftTemplateTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config(['attendance.timezone' => 'Asia/Phnom_Penh']);
    }

    public function test_manager_can_manage_shift_templates(): void
    {
        $this->actingAsRole('Head Nurse');

        $response = $this->postJson('/api/v1/shift-templates', [
            'name' => 'Day Shift',
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
            'clock_out_opens_at' => '14:30',
            'clock_out_closes_at' => '15:00',
            'sort_order' => 1,
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->getJson('/api/v1/shift-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/shift-templates/{$id}", ['name' => 'Day Shift (updated)'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Day Shift (updated)');

        $this->deleteJson("/api/v1/shift-templates/{$id}")->assertOk();
        $this->assertDatabaseMissing('shift_templates', ['id' => $id]);
    }

    public function test_template_names_are_unique(): void
    {
        $this->actingAsRole('Head Nurse');
        $payload = [
            'name' => 'Duplicate '.Str::random(4),
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
        ];

        $this->postJson('/api/v1/shift-templates', $payload)->assertCreated();
        $this->postJson('/api/v1/shift-templates', $payload)->assertStatus(422);
    }

    public function test_staff_cannot_manage_shift_templates(): void
    {
        $this->actingAsRole('Nurse');
        $template = ShiftTemplate::factory()->create(['name' => 'Morning '.Str::random(6)]);

        $this->postJson('/api/v1/shift-templates', [
            'name' => 'Nurse Attempt',
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
        ])->assertForbidden();

        $this->putJson("/api/v1/shift-templates/{$template->id}", ['name' => 'Hacked'])->assertForbidden();
    }

    public function test_staff_can_view_active_templates(): void
    {
        $this->actingAsRole('Nurse');
        ShiftTemplate::factory()->create(['name' => 'Active '.Str::random(4), 'is_active' => true]);

        $this->getJson('/api/v1/shift-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
