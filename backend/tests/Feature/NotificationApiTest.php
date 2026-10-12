<?php

namespace Tests\Feature;

use App\Notifications\NewMessageNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function notification(): NewMessageNotification
    {
        return new NewMessageNotification(
            conversationId: 1,
            conversationTitle: 'Ward Handover',
            messageId: 5,
            senderName: 'Dr. Strange',
            preview: 'Patient needs review',
            isGroup: true,
        );
    }

    public function test_user_can_list_and_count_notifications(): void
    {
        $user = $this->actingAsRole('Nurse');
        $user->notify($this->notification());

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'new_message')
            ->assertJsonPath('data.0.data.conversation_id', 1);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = $this->actingAsRole('Nurse');
        $user->notify($this->notification());

        $id = $user->notifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->actingAsRole('Nurse');
        $user->notify($this->notification());
        $user->notify($this->notification());

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_notifications_are_scoped_to_the_authenticated_user(): void
    {
        $nurse = $this->userWithRole('Nurse');
        $nurse->notify($this->notification());
        $other = $this->actingAsRole('Doctor');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $id = $nurse->notifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
    }

    public function test_user_can_delete_a_notification(): void
    {
        $user = $this->actingAsRole('Nurse');
        $user->notify($this->notification());

        $id = $user->notifications()->first()->id;

        $this->deleteJson("/api/v1/notifications/{$id}")->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_preferences_default_and_can_be_updated(): void
    {
        $user = $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.new_message', true)
            ->assertJsonPath('data.telegram', true);

        $this->putJson('/api/v1/notification-preferences', [
            'new_message' => false,
            'telegram' => false,
        ])->assertOk()
            ->assertJsonPath('data.new_message', false)
            ->assertJsonPath('data.telegram', false)
            ->assertJsonPath('data.group_updates', true);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'new_message' => false,
            'telegram' => false,
        ]);
    }

    public function test_patients_cannot_access_notifications(): void
    {
        $this->actingAsRole('Patient');

        $this->getJson('/api/v1/notifications')->assertForbidden();
        $this->getJson('/api/v1/notification-preferences')->assertForbidden();
    }
}
