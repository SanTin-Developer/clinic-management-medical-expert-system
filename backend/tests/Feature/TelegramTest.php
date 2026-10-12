<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Conversation;
use App\Models\TelegramConnection;
use App\Models\TelegramGroupConfig;
use App\Models\TelegramLinkingToken;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function enableTelegram(): void
    {
        config([
            'communication.telegram.enabled' => true,
            'communication.telegram.bot_token' => '123456:TEST-TOKEN',
            'communication.telegram.bot_username' => 'royalcare_test_bot',
            'communication.telegram.webhook_enabled' => true,
            'communication.telegram.webhook_secret' => 'super-secret',
        ]);
    }

    public function test_staff_can_view_telegram_status(): void
    {
        $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/telegram/status')
            ->assertOk()
            ->assertJsonPath('data.linked', false);
    }

    public function test_linking_generates_a_deep_link_and_hashed_token(): void
    {
        $this->enableTelegram();
        $nurse = $this->actingAsRole('Nurse');

        $response = $this->postJson('/api/v1/telegram/link')->assertOk();

        $token = $response->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertStringContainsString('royalcare_test_bot', $response->json('data.deep_link'));
        $this->assertDatabaseHas('telegram_linking_tokens', [
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('telegram_linking_tokens', ['token_hash' => $token]);
    }

    public function test_webhook_links_an_account_from_a_start_command(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        $token = 'link-token-value';
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => [
                'text' => '/start '.$token,
                'chat' => ['id' => 555000],
                'from' => ['username' => 'nurse_jane'],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'super-secret'])->assertOk();

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $nurse->id,
            'telegram_chat_id' => 555000,
            'telegram_username' => 'nurse_jane',
        ]);
        $this->assertNotNull(TelegramLinkingToken::first()->used_at);
    }

    public function test_webhook_rejects_requests_without_the_secret_header(): void
    {
        $this->enableTelegram();

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['text' => '/start x', 'chat' => ['id' => 1]],
        ])->assertForbidden();

        $this->assertDatabaseCount('telegram_connections', 0);
    }

    public function test_webhook_does_not_link_with_an_expired_token(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => [
                'text' => '/start expired-token',
                'chat' => ['id' => 777],
                'from' => ['username' => 'nurse_jane'],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'super-secret'])->assertOk();

        $this->assertDatabaseCount('telegram_connections', 0);
    }

    public function test_stop_command_unlinks_a_chat(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramConnection::create([
            'user_id' => $nurse->id,
            'telegram_chat_id' => 12321,
            'verified_at' => now(),
        ]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['text' => '/stop', 'chat' => ['id' => 12321]],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'super-secret'])->assertOk();

        $this->assertDatabaseCount('telegram_connections', 0);
    }

    public function test_staff_can_unlink_their_account(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        TelegramConnection::create([
            'user_id' => $nurse->id,
            'telegram_chat_id' => 42,
            'verified_at' => now(),
        ]);

        $this->deleteJson('/api/v1/telegram/link')->assertOk();

        $this->assertDatabaseCount('telegram_connections', 0);
    }

    public function test_admin_can_manage_group_configuration(): void
    {
        $this->actingAsRole('Admin');

        $this->putJson('/api/v1/telegram/group-config', [
            'chat_id' => 999123,
            'is_enabled' => true,
            'event_categories' => ['new_message', 'announcements'],
        ])->assertOk()
            ->assertJsonPath('data.chat_id', 999123)
            ->assertJsonPath('data.is_enabled', true);

        $this->assertDatabaseHas('telegram_group_configs', [
            'chat_id' => 999123,
            'is_enabled' => true,
        ]);
    }

    public function test_non_admin_cannot_manage_group_configuration(): void
    {
        $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/telegram/group-config')->assertForbidden();
        $this->putJson('/api/v1/telegram/group-config', ['chat_id' => 1])->assertForbidden();
    }

    public function test_patients_cannot_use_telegram_features(): void
    {
        $this->actingAsRole('Patient');

        $this->getJson('/api/v1/telegram/status')->assertForbidden();
        $this->postJson('/api/v1/telegram/link')->assertForbidden();
    }

    public function test_messages_dispatch_telegram_notifications_to_linked_users(): void
    {
        Queue::fake();
        $this->enableTelegram();

        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');
        TelegramConnection::create([
            'user_id' => $doctor->id,
            'telegram_chat_id' => 888777,
            'verified_at' => now(),
        ]);

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Please review bed 4.',
        ])->assertCreated();

        Queue::assertPushed(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job) => $job->chatId === 888777 && $job->userId === $doctor->id,
        );
    }

    public function test_messages_dispatch_to_the_configured_group_chat(): void
    {
        Queue::fake();
        $this->enableTelegram();

        TelegramGroupConfig::create([
            'chat_id' => 111222,
            'is_enabled' => true,
            'event_categories' => ['new_message'],
        ]);

        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_GROUP,
            'title' => 'Ward',
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Handover note.',
        ])->assertCreated();

        Queue::assertPushed(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job) => $job->chatId === 111222 && $job->userId === null,
        );
    }
}
