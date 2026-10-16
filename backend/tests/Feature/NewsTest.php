<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NewsTest extends TestCase
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

    public function test_news_lists_only_announcements_visible_to_the_users_audience(): void
    {
        Sanctum::actingAs($this->userWithRole('Doctor'));

        Announcement::factory()->published()->create(['audience' => 'all', 'title' => 'Everyone']);
        Announcement::factory()->published()->create(['audience' => 'staff', 'title' => 'Staff only']);
        Announcement::factory()->published()->create(['audience' => 'doctors', 'title' => 'Doctors only']);
        Announcement::factory()->published()->create(['audience' => 'nurses', 'title' => 'Nurses only']);
        Announcement::factory()->published()->create(['audience' => 'patients', 'title' => 'Patients only']);

        $response = $this->getJson('/api/v1/news')->assertOk();

        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Everyone'));
        $this->assertTrue($titles->contains('Staff only'));
        $this->assertTrue($titles->contains('Doctors only'));
        $this->assertFalse($titles->contains('Nurses only'));
        $this->assertFalse($titles->contains('Patients only'));
    }

    public function test_news_excludes_drafts_scheduled_expired_and_archived(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        Announcement::factory()->published()->create(['audience' => 'all', 'title' => 'Live']);
        Announcement::factory()->create(['audience' => 'all', 'title' => 'Draft']);
        Announcement::factory()->create(['audience' => 'all', 'title' => 'Scheduled', 'published_at' => now()->addDay()]);
        Announcement::factory()->published()->create(['audience' => 'all', 'title' => 'Expired', 'expires_at' => now()->subDay()]);
        Announcement::factory()->published()->create(['audience' => 'all', 'title' => 'Archived', 'archived_at' => now()]);

        $titles = collect($this->getJson('/api/v1/news')->assertOk()->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Live'));
        $this->assertFalse($titles->contains('Draft'));
        $this->assertFalse($titles->contains('Scheduled'));
        $this->assertFalse($titles->contains('Expired'));
        $this->assertFalse($titles->contains('Archived'));
    }

    public function test_news_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/news')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/news')->assertForbidden();
    }

    public function test_opening_an_announcement_marks_it_read_idempotently(): void
    {
        $nurse = $this->userWithRole('Nurse');
        Sanctum::actingAs($nurse);

        $announcement = Announcement::factory()->published()->create(['audience' => 'all']);

        $this->getJson("/api/v1/news/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('is_read', true);

        $this->getJson("/api/v1/news/{$announcement->id}")->assertOk();

        $this->assertDatabaseCount('announcement_reads', 1);
    }

    public function test_unread_count_and_mark_all_read(): void
    {
        $nurse = $this->userWithRole('Nurse');
        Sanctum::actingAs($nurse);

        Announcement::factory()->published()->count(3)->create(['audience' => 'all']);
        Announcement::factory()->published()->create(['audience' => 'patients']);

        $this->getJson('/api/v1/news/unread-count')->assertOk()->assertJsonPath('unread_count', 3);

        $this->postJson('/api/v1/news/read-all')->assertOk()->assertJsonPath('unread_count', 0);

        $this->assertDatabaseCount('announcement_reads', 3);
        $this->getJson('/api/v1/news/unread-count')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_read_state_is_isolated_per_user(): void
    {
        $nurse = $this->userWithRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $announcement = Announcement::factory()->published()->create(['audience' => 'all']);

        Sanctum::actingAs($nurse);
        $this->getJson("/api/v1/news/{$announcement->id}")->assertOk();

        Sanctum::actingAs($doctor);
        $this->getJson('/api/v1/news/unread-count')->assertOk()->assertJsonPath('unread_count', 1);
    }

    public function test_reader_cannot_view_an_announcement_outside_their_audience(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $announcement = Announcement::factory()->published()->create(['audience' => 'doctors']);

        $this->getJson("/api/v1/news/{$announcement->id}")->assertNotFound();
    }

    public function test_urgent_announcements_are_listed_first_and_exposed_as_featured(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        Announcement::factory()->published()->create(['audience' => 'all', 'priority' => 'normal', 'title' => 'Normal']);
        Announcement::factory()->published()->create(['audience' => 'all', 'priority' => 'urgent', 'title' => 'Urgent']);
        Announcement::factory()->published()->create(['audience' => 'all', 'priority' => 'high', 'title' => 'High']);

        $response = $this->getJson('/api/v1/news')->assertOk();

        $this->assertSame('Urgent', $response->json('data.0.title'));
        $this->assertSame('Urgent', $response->json('featured.title'));
    }

    public function test_mark_read_endpoint_does_not_allow_marking_an_invisible_announcement(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $announcement = Announcement::factory()->published()->create(['audience' => 'patients']);

        $this->postJson("/api/v1/news/{$announcement->id}/read")->assertNotFound();
        $this->assertDatabaseCount('announcement_reads', 0);
    }
}
