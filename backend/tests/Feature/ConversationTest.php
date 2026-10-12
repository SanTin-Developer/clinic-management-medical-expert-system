<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Notifications\NewMessageNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_can_start_a_direct_conversation_and_exchange_messages(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $response = $this->postJson('/api/v1/conversations', [
            'type' => 'private',
            'user_id' => $doctor->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_group', false)
            ->assertJsonPath('data.type', 'private');

        $conversationId = $response->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Patient in room 3 needs review.',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Patient in room 3 needs review.');

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_messages', 1);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversationId)
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.title', $nurse->name);
    }

    public function test_private_conversation_is_reused_between_the_same_users(): void
    {
        $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $first = $this->postJson('/api/v1/conversations', [
            'type' => 'private',
            'user_id' => $doctor->id,
        ])->assertCreated()->json('data.id');

        $second = $this->postJson('/api/v1/conversations', [
            'type' => 'private',
            'user_id' => $doctor->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_staff_can_create_a_group_conversation(): void
    {
        $this->actingAsRole('Doctor');
        $nurse = $this->userWithRole('Nurse');
        $pharmacist = $this->userWithRole('Pharmacist');

        $this->postJson('/api/v1/conversations', [
            'type' => 'group',
            'title' => 'Ward Handover',
            'participant_ids' => [$nurse->id, $pharmacist->id],
        ])->assertCreated()
            ->assertJsonPath('data.is_group', true)
            ->assertJsonPath('data.title', 'Ward Handover')
            ->assertJsonCount(3, 'data.participants');

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_non_participant_cannot_view_a_conversation(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $stranger = $this->userWithRole('Nurse');
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Intrusion',
        ])->assertForbidden();
    }

    public function test_sending_a_message_notifies_the_other_participants(): void
    {
        Notification::fake();

        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Please review the labs.',
        ])->assertCreated();

        Notification::assertSentTo($doctor, NewMessageNotification::class);
        Notification::assertNotSentTo($nurse, NewMessageNotification::class);
    }

    public function test_marking_a_conversation_read_resets_the_unread_count(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Hello doctor.',
        ])->assertCreated();

        Sanctum::actingAs($doctor);

        $this->postJson("/api/v1/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_group_participants_can_be_added_and_removed(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $nurse = $this->userWithRole('Nurse');
        $pharmacist = $this->userWithRole('Pharmacist');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_GROUP,
            'title' => 'Care Team',
            'created_by' => $doctor->id,
        ]);
        $conversation->participants()->create(['user_id' => $doctor->id]);
        $conversation->participants()->create(['user_id' => $nurse->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/participants", [
            'participant_ids' => [$pharmacist->id],
        ])->assertOk()->assertJsonCount(3, 'data.participants');

        $this->deleteJson("/api/v1/conversations/{$conversation->id}/participants/{$nurse->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.participants');
    }

    public function test_participant_can_leave_a_group_conversation(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $nurse = $this->userWithRole('Nurse');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_GROUP,
            'title' => 'Care Team',
            'created_by' => $doctor->id,
        ]);
        $conversation->participants()->create(['user_id' => $doctor->id]);
        $conversation->participants()->create(['user_id' => $nurse->id]);

        Sanctum::actingAs($nurse);

        $this->deleteJson("/api/v1/conversations/{$conversation->id}/participants/{$nurse->id}")
            ->assertOk()
            ->assertJsonPath('message', 'You left the conversation.');

        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertForbidden();
    }

    public function test_patients_cannot_access_staff_chat(): void
    {
        $this->actingAsRole('Patient');

        $this->getJson('/api/v1/conversations')->assertForbidden();
        $this->postJson('/api/v1/conversations', [
            'type' => 'private',
            'user_id' => 1,
        ])->assertForbidden();
    }

    public function test_chat_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    }

    public function test_recipients_lists_staff_only(): void
    {
        $this->actingAsRole('Nurse');
        $this->userWithRole('Doctor');
        $this->userWithRole('Patient');

        $response = $this->getJson('/api/v1/conversations/recipients')->assertOk();

        $roles = collect($response->json('data'))->pluck('role');

        $this->assertTrue($roles->contains('Doctor'));
        $this->assertFalse($roles->contains('Patient'));
    }
}
