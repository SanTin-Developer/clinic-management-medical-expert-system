<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Announcement;
use App\Models\NotificationPreference;
use App\Models\TelegramConnection;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\Announcements\AnnouncementPosterStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    private function enableTelegram(): void
    {
        config([
            'communication.telegram.enabled' => true,
            'communication.telegram.bot_token' => '123456:TEST-TOKEN',
            'communication.telegram.bot_username' => 'royalcare_test_bot',
        ]);
    }

    private function linkTelegram(User $user, int $chatId): void
    {
        TelegramConnection::create([
            'user_id' => $user->id,
            'telegram_chat_id' => $chatId,
            'verified_at' => now(),
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function useCloudinary(): void
    {
        config([
            'documents.poster_disk' => 'cloudinary',
            'services.cloudinary' => [
                'cloud_name' => 'demo',
                'api_key' => 'key',
                'api_secret' => 'secret',
                'folder' => 'clinic',
                'secure' => true,
            ],
        ]);

        app()->forgetInstance(AnnouncementPosterStorage::class);
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

    public function test_nurse_can_read_news_but_cannot_manage_announcements(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        // Readers use the audience-filtered News endpoint, not the management listing.
        $this->getJson('/api/v1/news')->assertOk();
        $this->getJson('/api/v1/announcements')->assertForbidden();

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

    public function test_creating_a_published_announcement_notifies_the_audience_once(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/announcements', [
            'title' => 'Live now',
            'body' => 'Body',
            'audience' => 'doctors',
            'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.is_published', true);

        Notification::assertSentToTimes($doctor, SystemAnnouncementNotification::class, 1);
    }

    public function test_creating_a_draft_does_not_publish_or_notify(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/announcements', [
            'title' => 'Draft',
            'body' => 'Body',
            'audience' => 'doctors',
            'is_published' => false,
        ])->assertCreated()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null);

        Notification::assertNothingSent();
        $this->assertSame(0, $doctor->unreadNotifications()->count());
    }

    public function test_scheduling_via_create_sets_a_future_publication_without_notifying(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Later',
            'body' => 'Body',
            'audience' => 'all',
            'is_published' => false,
            'published_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.is_published', false);

        Notification::assertNothingSent();
        $this->getJson('/api/v1/announcements?status=scheduled')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_schedule_endpoint_rejects_a_past_date(): void
    {
        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        $announcement = Announcement::factory()->create(['created_by' => $admin->id]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/schedule", [
            'published_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_due_scheduled_announcements_are_published_by_the_command_without_duplicates(): void
    {
        Notification::fake();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'published_at' => now()->subMinute(),
            'created_by' => $admin->id,
        ]);

        $this->artisan('announcements:publish-scheduled')->assertSuccessful();

        $this->assertTrue($announcement->fresh()->is_published);
        Notification::assertSentToTimes($doctor, SystemAnnouncementNotification::class, 1);

        // Running again must not re-notify.
        $this->artisan('announcements:publish-scheduled')->assertSuccessful();
        Notification::assertSentToTimes($doctor, SystemAnnouncementNotification::class, 1);
    }

    public function test_expired_announcement_is_filtered_and_hidden_from_news(): void
    {
        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        Announcement::factory()->published()->create([
            'audience' => 'all',
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/announcements?status=expired')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/v1/news')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_announcement_can_be_archived_and_restored(): void
    {
        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        $announcement = Announcement::factory()->published()->create(['audience' => 'all']);

        $this->postJson("/api/v1/announcements/{$announcement->id}/archive")->assertOk();
        $this->assertNotNull($announcement->fresh()->archived_at);

        $this->getJson('/api/v1/news')->assertOk()->assertJsonPath('total', 0);

        $this->postJson("/api/v1/announcements/{$announcement->id}/restore")->assertOk();
        $this->assertNull($announcement->fresh()->archived_at);
    }

    public function test_readership_reports_read_and_unread_recipients(): void
    {
        $admin = $this->userWithRole('Admin');
        $nurse = $this->userWithRole('Nurse');

        $announcement = Announcement::factory()->published()->create([
            'audience' => 'nurses',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($nurse);
        $this->getJson("/api/v1/news/{$announcement->id}")->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/announcements/{$announcement->id}/readership")
            ->assertOk()
            ->assertJsonPath('data.recipients', 1)
            ->assertJsonPath('data.read', 1)
            ->assertJsonPath('data.unread', 0);
    }

    public function test_readership_and_audit_are_forbidden_for_non_managers(): void
    {
        $nurse = $this->userWithRole('Nurse');
        Sanctum::actingAs($nurse);

        $announcement = Announcement::factory()->published()->create(['audience' => 'all']);

        $this->getJson("/api/v1/announcements/{$announcement->id}/readership")->assertForbidden();
        $this->getJson("/api/v1/announcements/{$announcement->id}/audit")->assertForbidden();
        $this->postJson("/api/v1/announcements/{$announcement->id}/archive")->assertForbidden();
    }

    public function test_editing_an_announcement_is_recorded_in_audit_history(): void
    {
        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        $announcement = Announcement::factory()->create(['created_by' => $admin->id]);

        $this->putJson("/api/v1/announcements/{$announcement->id}", [
            'title' => 'Updated title',
        ])->assertOk();

        $response = $this->getJson("/api/v1/announcements/{$announcement->id}/audit")->assertOk();

        $events = collect($response->json('data'))->pluck('event');

        $this->assertTrue($events->contains('created'));
        $this->assertTrue($events->contains('updated'));
    }

    public function test_creating_an_announcement_validates_required_fields(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->postJson('/api/v1/announcements', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body', 'audience']);
    }

    public function test_creating_an_announcement_stores_and_serves_a_poster(): void
    {
        Storage::fake(Announcement::POSTER_DISK);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $response = $this->post('/api/v1/announcements', [
            'title' => 'Poster news',
            'body' => 'Body',
            'audience' => 'all',
            'poster' => $this->fakePng('poster.png'),
        ])->assertCreated();

        $announcement = Announcement::query()->firstOrFail();

        $this->assertNotNull($announcement->poster_path);
        Storage::disk(Announcement::POSTER_DISK)->assertExists($announcement->poster_path);

        $posterUrl = $response->json('data.poster_url');
        $this->assertNotNull($posterUrl);

        $this->get(parse_url($posterUrl, PHP_URL_PATH))->assertOk();
    }

    public function test_poster_can_be_replaced_and_removed(): void
    {
        Storage::fake(Announcement::POSTER_DISK);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $announcement = Announcement::factory()->create(['created_by' => null]);

        $this->post("/api/v1/announcements/{$announcement->id}", [
            '_method' => 'PUT',
            'poster' => $this->fakePng('first.png'),
        ])->assertOk();

        $firstPath = $announcement->fresh()->poster_path;
        $this->assertNotNull($firstPath);

        $this->post("/api/v1/announcements/{$announcement->id}", [
            '_method' => 'PUT',
            'poster' => $this->fakePng('second.png'),
        ])->assertOk();

        $secondPath = $announcement->fresh()->poster_path;
        $this->assertNotNull($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk(Announcement::POSTER_DISK)->assertMissing($firstPath);
        Storage::disk(Announcement::POSTER_DISK)->assertExists($secondPath);

        $this->post("/api/v1/announcements/{$announcement->id}", [
            '_method' => 'PUT',
            'remove_poster' => true,
        ])->assertOk();

        $this->assertNull($announcement->fresh()->poster_path);
        Storage::disk(Announcement::POSTER_DISK)->assertMissing($secondPath);
    }

    public function test_poster_route_returns_not_found_when_absent(): void
    {
        $announcement = Announcement::factory()->create();

        $this->getJson("/api/v1/announcements/{$announcement->id}/poster")->assertNotFound();
    }

    public function test_poster_upload_rejects_non_image_content(): void
    {
        Storage::fake(Announcement::POSTER_DISK);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->post('/api/v1/announcements', [
            'title' => 'Bad poster',
            'body' => 'Body',
            'audience' => 'all',
            'poster' => UploadedFile::fake()->create('poster.png', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('poster');
    }

    public function test_deleting_an_announcement_removes_its_poster(): void
    {
        Storage::fake(Announcement::POSTER_DISK);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $announcement = Announcement::factory()->create(['created_by' => null]);

        $this->post("/api/v1/announcements/{$announcement->id}", [
            '_method' => 'PUT',
            'poster' => $this->fakePng('poster.png'),
        ])->assertOk();

        $path = $announcement->fresh()->poster_path;
        $this->assertNotNull($path);

        $this->deleteJson("/api/v1/announcements/{$announcement->id}")->assertOk();

        Storage::disk(Announcement::POSTER_DISK)->assertMissing($path);
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_poster_upload_uses_cloudinary_when_configured(): void
    {
        $secureUrl = 'https://res.cloudinary.com/demo/image/upload/v1/clinic/announcement-posters/poster.png';

        $this->useCloudinary();

        Http::fake([
            'api.cloudinary.com/*' => Http::response(['secure_url' => $secureUrl], 200),
        ]);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $response = $this->post('/api/v1/announcements', [
            'title' => 'Cloud poster',
            'body' => 'Body',
            'audience' => 'all',
            'poster' => $this->fakePng('poster.png'),
        ])->assertCreated();

        $announcement = Announcement::query()->firstOrFail();

        $this->assertSame($secureUrl, $announcement->poster_path);
        $this->assertSame($secureUrl, $response->json('data.poster_url'));

        $this->get("/api/v1/announcements/{$announcement->id}/poster")->assertRedirect($secureUrl);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.cloudinary.com')
            && str_contains($request->url(), '/image/upload'));
    }

    public function test_removing_a_cloudinary_poster_deletes_it_from_cloudinary(): void
    {
        $secureUrl = 'https://res.cloudinary.com/demo/image/upload/v1/clinic/announcement-posters/old.png';

        $this->useCloudinary();

        Http::fake([
            'api.cloudinary.com/*' => Http::response(['result' => 'ok'], 200),
        ]);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $announcement = Announcement::factory()->create([
            'created_by' => null,
            'poster_path' => $secureUrl,
        ]);

        $this->putJson("/api/v1/announcements/{$announcement->id}", [
            'remove_poster' => true,
        ])->assertOk();

        $this->assertNull($announcement->fresh()->poster_path);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/image/destroy')
            && $request['public_id'] === 'clinic/announcement-posters/old');
    }

    public function test_publishing_broadcasts_to_linked_telegram_users(): void
    {
        Queue::fake([SendTelegramMessage::class]);
        $this->enableTelegram();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 888777);

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertPushed(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job): bool => $job->chatId === 888777
                && $job->userId === $doctor->id
                && $job->eventType === 'announcement'
                && $job->idempotencyKey === "announcement:{$announcement->id}:user:{$doctor->id}"
                && $job->photoPath === null,
        );
    }

    public function test_publishing_broadcast_includes_the_poster(): void
    {
        Queue::fake([SendTelegramMessage::class]);
        $this->enableTelegram();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 444555);

        $announcement = Announcement::factory()->create([
            'audience' => 'all',
            'is_published' => false,
            'created_by' => $admin->id,
            'poster_path' => 'announcement-posters/photo.png',
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertPushed(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job): bool => $job->photoDisk === Announcement::POSTER_DISK
                && $job->photoPath === 'announcement-posters/photo.png',
        );
    }

    public function test_publishing_broadcast_uses_the_cloudinary_url_for_the_poster(): void
    {
        Queue::fake([SendTelegramMessage::class]);
        $this->useCloudinary();
        $this->enableTelegram();

        $secureUrl = 'https://res.cloudinary.com/demo/image/upload/v1/clinic/announcement-posters/poster.png';

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 654321);

        $announcement = Announcement::factory()->create([
            'audience' => 'all',
            'is_published' => false,
            'created_by' => $admin->id,
            'poster_path' => $secureUrl,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertPushed(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job): bool => $job->photoUrl === $secureUrl
                && $job->photoPath === null
                && $job->photoDisk === null,
        );
    }

    public function test_telegram_broadcast_targets_only_the_audience(): void
    {
        Queue::fake([SendTelegramMessage::class]);
        $this->enableTelegram();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $nurse = $this->userWithRole('Nurse');
        $this->linkTelegram($doctor, 111);
        $this->linkTelegram($nurse, 222);

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->chatId === 111);
        Queue::assertNotPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->chatId === 222);
    }

    public function test_telegram_broadcast_honours_the_opt_out_preference(): void
    {
        Queue::fake([SendTelegramMessage::class]);
        $this->enableTelegram();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 999);
        NotificationPreference::forUser($doctor)->update(['telegram' => false]);

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertNotPushed(SendTelegramMessage::class);
    }

    public function test_telegram_broadcast_is_skipped_when_not_configured(): void
    {
        Queue::fake([SendTelegramMessage::class]);

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 777);

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Queue::assertNotPushed(SendTelegramMessage::class);
    }

    public function test_publishing_with_a_poster_sends_a_telegram_photo(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 55]])]);
        Storage::fake(Announcement::POSTER_DISK);
        $this->enableTelegram();

        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);
        $doctor = $this->userWithRole('Doctor');
        $this->linkTelegram($doctor, 321654);

        Storage::disk(Announcement::POSTER_DISK)->put('announcement-posters/photo.png', 'binary-content');

        $announcement = Announcement::factory()->create([
            'audience' => 'doctors',
            'is_published' => false,
            'created_by' => $admin->id,
            'poster_path' => 'announcement-posters/photo.png',
        ]);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendPhoto'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'sendMessage'));
    }
}
