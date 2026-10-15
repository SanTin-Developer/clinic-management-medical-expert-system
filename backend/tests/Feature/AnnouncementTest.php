<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnnouncementTest extends TestCase
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

    public function test_admin_can_create_and_list_announcements(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->postJson('/api/v1/announcements', [
            'title' => 'Clinic closed on Friday',
            'body' => 'The clinic will be closed for maintenance.',
            'audience' => 'staff',
            'priority' => 'high',
        ])->assertCreated()->assertJsonPath('data.title', 'Clinic closed on Friday');

        $this->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.draft', 1);
    }

    public function test_nurse_can_view_but_not_create_announcements(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $this->getJson('/api/v1/announcements')->assertOk();

        $this->postJson('/api/v1/announcements', [
            'title' => 'Nope',
            'body' => 'Should not be allowed.',
            'audience' => 'staff',
        ])->assertForbidden();
    }

    public function test_user_without_permission_cannot_list_announcements(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/announcements')->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_announcements(): void
    {
        $this->getJson('/api/v1/announcements')->assertUnauthorized();
    }

    public function test_publishing_an_announcement_notifies_its_audience(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        Notification::assertSentTo($doctor, SystemAnnouncementNotification::class);
        Notification::assertNotSentTo($admin, SystemAnnouncementNotification::class);
    }

    public function test_publishing_all_staff_announcement_excludes_author(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $nurse = $this->userWithRole('Nurse');

        $announcement = Announcement::factory()->create([
            'audience' => 'all',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Notification::assertSentTo($nurse, SystemAnnouncementNotification::class);
        Notification::assertNotSentTo($admin, SystemAnnouncementNotification::class);
    }

    public function test_publishing_twice_does_not_duplicate_notifications(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();
        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Notification::assertSentToTimes($doctor, SystemAnnouncementNotification::class, 1);
    }

    public function test_index_filters_by_status(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        Announcement::factory()->published()->create();
        Announcement::factory()->create();

        $this->getJson('/api/v1/announcements?status=published')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.is_published', true);
    }
}
