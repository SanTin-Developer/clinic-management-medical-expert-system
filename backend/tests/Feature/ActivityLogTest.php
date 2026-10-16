<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Services\Audit\ActivityLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activity(array $attributes = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'event' => ActivityLog::EVENT_CREATED,
            'description' => 'Created Ward',
            'user_name' => 'Jane Doe',
            'user_role' => 'Admin',
        ], $attributes));
    }

    public function test_admin_can_list_activity_logs(): void
    {
        $this->actingAsRole('Admin');
        $this->activity();

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.description', 'Created Ward');
    }

    public function test_index_returns_activity_statistics(): void
    {
        $admin = $this->actingAsRole('Admin');
        $this->activity(['user_id' => $admin->id, 'event' => ActivityLog::EVENT_CREATED]);
        $this->activity(['user_id' => $admin->id, 'event' => ActivityLog::EVENT_UPDATED]);

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.last_24h', 2)
            ->assertJsonPath('stats.users', 1)
            ->assertJsonCount(2, 'stats.events');
    }

    public function test_index_filters_by_event(): void
    {
        $this->actingAsRole('Admin');
        $this->activity(['event' => ActivityLog::EVENT_CREATED]);
        $this->activity(['event' => ActivityLog::EVENT_DELETED]);

        $this->getJson('/api/v1/audit-logs?event=deleted')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.event', 'deleted');
    }

    public function test_admin_can_view_a_single_activity_log(): void
    {
        $this->actingAsRole('Admin');
        $log = $this->activity(['description' => 'Signed in']);

        $this->getJson("/api/v1/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Signed in');
    }

    public function test_roles_without_audit_permission_are_forbidden(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_model_changes_are_recorded_for_authenticated_users(): void
    {
        $admin = $this->actingAsRole('Admin');

        $announcement = Announcement::factory()->create(['title' => 'Clinic closed']);
        $announcement->update(['title' => 'Clinic reopened']);
        $announcement->delete();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => ActivityLog::EVENT_CREATED,
            'subject_type' => Announcement::class,
            'subject_id' => $announcement->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => ActivityLog::EVENT_UPDATED,
            'subject_type' => Announcement::class,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => ActivityLog::EVENT_DELETED,
            'subject_type' => Announcement::class,
        ]);
    }

    public function test_model_changes_are_not_recorded_for_guests(): void
    {
        Announcement::factory()->create();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_logger_attributes_the_authenticated_user(): void
    {
        $admin = $this->actingAsRole('Admin');

        app(ActivityLogger::class)->record(ActivityLog::EVENT_LOGIN, 'Signed in');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'event' => ActivityLog::EVENT_LOGIN,
        ]);
    }
}
