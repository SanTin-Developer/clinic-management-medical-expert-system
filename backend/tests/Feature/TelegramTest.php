<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TelegramConnection;
use App\Models\TelegramDeliveryLog;
use App\Models\TelegramGroupConfig;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use App\Services\Telegram\TelegramLinkService;
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
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'expired'));
    }

    private function postUpdate(array $update): void
    {
        $this->postJson('/api/v1/telegram/webhook', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'super-secret',
        ])->assertOk();
    }

    public function test_generated_code_is_compatible_with_telegram_payload_limits(): void
    {
        $this->enableTelegram();
        $this->actingAsRole('Nurse');

        $response = $this->postJson('/api/v1/telegram/link')->assertOk();

        $token = (string) $response->json('data.token');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,64}$/', $token);
        $this->assertLessThanOrEqual(64, strlen($token));
        $this->assertStringStartsWith(
            'https://t.me/royalcare_test_bot?start=',
            (string) $response->json('data.deep_link'),
        );
    }

    public function test_webhook_always_returns_ok_even_for_unhandled_updates(): void
    {
        $this->enableTelegram();

        $this->postUpdate(['update_id' => 100, 'edited_message' => ['text' => 'ignored']]);

        $this->assertDatabaseCount('telegram_connections', 0);
    }

    public function test_start_without_code_replies_with_welcome_and_does_not_link(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate(['update_id' => 101, 'message' => ['text' => '/start', 'chat' => ['id' => 5001]]]);

        $this->assertDatabaseCount('telegram_connections', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'Welcome to RoyalCare'));
    }

    public function test_webhook_answers_invalid_code(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate([
            'update_id' => 102,
            'message' => ['text' => '/start not-a-real-code', 'chat' => ['id' => 5002]],
        ]);

        $this->assertDatabaseCount('telegram_connections', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'invalid'));
    }

    public function test_webhook_answers_already_used_code(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'used-token'),
            'expires_at' => now()->addMinutes(15),
            'used_at' => now()->subMinute(),
        ]);

        $this->postUpdate([
            'update_id' => 103,
            'message' => ['text' => '/start used-token', 'chat' => ['id' => 5003]],
        ]);

        $this->assertDatabaseCount('telegram_connections', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'already been used'));
    }

    public function test_replayed_update_is_processed_only_once(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'replay-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $update = [
            'update_id' => 9001,
            'message' => [
                'text' => '/start replay-token',
                'chat' => ['id' => 6001],
                'from' => ['username' => 'nurse_jane'],
            ],
        ];

        $this->postUpdate($update);
        $this->postUpdate($update);

        $this->assertDatabaseCount('telegram_connections', 1);
        Http::assertSentCount(1);
    }

    public function test_webhook_rejects_when_webhook_mode_is_disabled(): void
    {
        $this->enableTelegram();
        config(['communication.telegram.webhook_enabled' => false]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['text' => '/start x', 'chat' => ['id' => 1]],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'super-secret'])->assertForbidden();
    }

    public function test_telegram_account_already_linked_to_another_user_is_rejected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $owner = $this->userWithRole('Doctor');
        TelegramConnection::create([
            'user_id' => $owner->id,
            'telegram_chat_id' => 7001,
            'verified_at' => now(),
        ]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'conflict-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9101,
            'message' => ['text' => '/start conflict-token', 'chat' => ['id' => 7001]],
        ]);

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $owner->id,
            'telegram_chat_id' => 7001,
        ]);
        $this->assertDatabaseMissing('telegram_connections', ['user_id' => $nurse->id]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'already linked'));
    }

    public function test_linking_binds_to_token_owner_and_ignores_client_supplied_user_id(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        $attacker = $this->userWithRole('Doctor');

        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'bind-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9201,
            'message' => [
                'text' => '/start bind-token',
                'chat' => ['id' => 8001],
                'from' => ['id' => $attacker->id, 'username' => 'attacker'],
                'user_id' => $attacker->id,
            ],
        ]);

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $nurse->id,
            'telegram_chat_id' => 8001,
        ]);
        $this->assertDatabaseMissing('telegram_connections', ['user_id' => $attacker->id]);
    }

    public function test_status_endpoint_reports_linked_after_webhook_linking(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'status-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9301,
            'message' => [
                'text' => '/start status-token',
                'chat' => ['id' => 8501],
                'from' => ['username' => 'nurse_jane'],
            ],
        ]);

        Sanctum::actingAs($nurse);

        $this->getJson('/api/v1/telegram/status')
            ->assertOk()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.telegram_username', 'nurse_jane');
    }

    public function test_start_command_with_bot_mention_is_parsed(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'mention-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9401,
            'message' => [
                'text' => '/start@royalcare_test_bot mention-token',
                'chat' => ['id' => 9501],
            ],
        ]);

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $nurse->id,
            'telegram_chat_id' => 9501,
        ]);
    }

    public function test_consume_token_is_single_use_and_idempotent_for_the_same_chat(): void
    {
        $this->enableTelegram();

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'single-use'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $service = app(TelegramLinkService::class);

        $first = $service->consumeToken('single-use', 9701, 'nurse_jane');
        $second = $service->consumeToken('single-use', 9701, 'nurse_jane');

        $this->assertSame(TelegramLinkService::STATUS_LINKED, $first['status']);
        $this->assertSame(TelegramLinkService::STATUS_LINKED, $second['status']);
        $this->assertDatabaseCount('telegram_connections', 1);

        $other = $service->consumeToken('single-use', 9799, 'someone_else');

        $this->assertSame(TelegramLinkService::STATUS_USED, $other['status']);
        $this->assertDatabaseCount('telegram_connections', 1);
    }

    public function test_resending_the_same_code_from_the_linked_chat_replies_connected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'resend-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9551,
            'message' => ['text' => '/start resend-token', 'chat' => ['id' => 9711], 'from' => ['username' => 'nurse_jane']],
        ]);
        $this->postUpdate([
            'update_id' => 9552,
            'message' => ['text' => '/start resend-token', 'chat' => ['id' => 9711], 'from' => ['username' => 'nurse_jane']],
        ]);

        $this->assertDatabaseCount('telegram_connections', 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'connected'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'already been used'));
    }

    public function test_poll_command_processes_pending_updates(): void
    {
        $this->enableTelegram();
        config(['communication.telegram.webhook_enabled' => false]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'poll-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        Http::fake([
            'api.telegram.org/*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '']]),
            'api.telegram.org/*/getUpdates' => Http::response(['ok' => true, 'result' => [[
                'update_id' => 11,
                'message' => [
                    'text' => '/start poll-token',
                    'chat' => ['id' => 9101],
                    'from' => ['username' => 'nurse_jane'],
                ],
            ]]]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])->assertExitCode(0);

        $this->assertDatabaseHas('telegram_connections', [
            'user_id' => $nurse->id,
            'telegram_chat_id' => 9101,
        ]);
    }

    public function test_poll_command_disables_itself_when_webhook_mode_is_enabled(): void
    {
        $this->enableTelegram();
        Http::fake();

        $this->artisan('telegram:poll', ['--once' => true])
            ->expectsOutputToContain('Webhook mode is enabled')
            ->assertExitCode(0);

        Http::assertNothingSent();
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
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'unlinked'));
    }

    public function test_successful_linking_replies_with_confirmation(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramLinkingToken::create([
            'user_id' => $nurse->id,
            'token_hash' => hash('sha256', 'success-token'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postUpdate([
            'update_id' => 9501,
            'message' => [
                'text' => '/start@royalcare_test_bot success-token',
                'chat' => ['id' => 9601],
                'from' => ['username' => 'nurse_jane'],
            ],
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'connected')
            && str_contains((string) $request['text'], '@nurse_jane'));
    }

    public function test_stop_command_replies_when_chat_is_not_linked(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate(['update_id' => 9502, 'message' => ['text' => '/stop', 'chat' => ['id' => 9602]]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'not linked'));
    }

    public function test_help_command_replies_with_command_list(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate(['update_id' => 9503, 'message' => ['text' => '/help', 'chat' => ['id' => 9603]]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], '/start')
            && str_contains((string) $request['text'], '/stop'));
    }

    public function test_unknown_command_replies_with_help(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate(['update_id' => 9504, 'message' => ['text' => '/notacommand', 'chat' => ['id' => 9604]]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'RoyalCare Clinic bot'));
    }

    public function test_plain_chat_text_from_an_unlinked_chat_is_guided_to_link(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate(['update_id' => 9505, 'message' => [
            'message_id' => 1,
            'text' => 'hello there',
            'chat' => ['id' => 9605],
        ]]);

        $this->assertDatabaseCount('conversation_messages', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'not linked'));
    }

    public function test_plain_text_without_a_reply_or_active_conversation_is_not_matched(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramConnection::create([
            'user_id' => $nurse->id,
            'telegram_chat_id' => 9606,
            'verified_at' => now(),
        ]);

        $this->postUpdate(['update_id' => 9506, 'message' => [
            'message_id' => 2,
            'text' => 'hello there',
            'chat' => ['id' => 9606],
        ]]);

        $this->assertDatabaseCount('conversation_messages', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'could not tell'));
    }

    public function test_new_message_notification_text_is_formatted_and_escaped(): void
    {
        Queue::fake();
        $this->enableTelegram();

        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');
        TelegramConnection::create([
            'user_id' => $doctor->id,
            'telegram_chat_id' => 888778,
            'verified_at' => now(),
        ]);

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id]);
        $conversation->participants()->create(['user_id' => $doctor->id]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Review <b>bed 4</b> now.',
        ])->assertCreated();

        Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job) {
            return str_contains($job->text, '💬')
                && str_contains($job->text, 'Review &lt;b&gt;bed 4&lt;/b&gt; now.')
                && ! str_contains($job->text, '<b>bed 4</b>');
        });
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

    /**
     * Link a chat to a user and register an outbound notification mapping so
     * that the chat is able to reply to the referenced conversation.
     *
     * @return array{0: User, 1: Conversation, 2: ConversationMessage}
     */
    private function linkedConversationWithMapping(int $chatId): array
    {
        $user = $this->userWithRole('Nurse');
        $other = $this->userWithRole('Doctor');

        TelegramConnection::create([
            'user_id' => $user->id,
            'telegram_chat_id' => $chatId,
            'verified_at' => now(),
        ]);

        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $other->id,
        ]);
        $conversation->participants()->create(['user_id' => $user->id, 'joined_at' => now()]);
        $conversation->participants()->create(['user_id' => $other->id, 'joined_at' => now()]);

        $message = $conversation->messages()->create([
            'sender_id' => $other->id,
            'body' => 'Please review bed 4.',
        ]);

        TelegramDeliveryLog::create([
            'user_id' => $user->id,
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => 555,
            'conversation_id' => $conversation->id,
            'conversation_message_id' => $message->id,
            'event_type' => 'new_message',
            'status' => TelegramDeliveryLog::STATUS_SENT,
        ]);

        return [$user, $conversation, $message];
    }

    public function test_staff_can_reply_to_a_telegram_notification(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4001);

        $this->postUpdate([
            'update_id' => 20001,
            'message' => [
                'message_id' => 900,
                'text' => 'On my way.',
                'chat' => ['id' => 4001],
                'from' => ['username' => 'nurse_jane'],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $nurse->id,
            'body' => 'On my way.',
            'source' => ConversationMessage::SOURCE_TELEGRAM,
            'telegram_chat_id' => 4001,
            'telegram_message_id' => 900,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'reply was sent'));
    }

    public function test_reply_from_an_unlinked_chat_is_rejected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postUpdate([
            'update_id' => 21001,
            'message' => [
                'message_id' => 910,
                'text' => 'On my way.',
                'chat' => ['id' => 4100],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        $this->assertDatabaseCount('conversation_messages', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'not linked'));
    }

    public function test_reply_from_a_non_participant_is_rejected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4200);

        $conversation->participants()->where('user_id', $nurse->id)->delete();

        $this->postUpdate([
            'update_id' => 22001,
            'message' => [
                'message_id' => 920,
                'text' => 'Let me in.',
                'chat' => ['id' => 4200],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        $this->assertDatabaseMissing('conversation_messages', [
            'body' => 'Let me in.',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'not a participant'));
    }

    public function test_reply_to_an_unknown_notification_is_rejected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        TelegramConnection::create([
            'user_id' => $nurse->id,
            'telegram_chat_id' => 4300,
            'verified_at' => now(),
        ]);

        $this->postUpdate([
            'update_id' => 23001,
            'message' => [
                'message_id' => 930,
                'text' => 'On my way.',
                'chat' => ['id' => 4300],
                'reply_to_message' => ['message_id' => 999999],
            ],
        ]);

        $this->assertDatabaseCount('conversation_messages', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'could not match'));
    }

    public function test_duplicate_inbound_telegram_message_is_stored_once(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation] = $this->linkedConversationWithMapping(4400);

        $reply = [
            'message_id' => 940,
            'text' => 'On my way.',
            'chat' => ['id' => 4400],
            'from' => ['username' => 'nurse_jane'],
            'reply_to_message' => ['message_id' => 555],
        ];

        $this->postUpdate(['update_id' => 24001, 'message' => $reply]);
        $this->postUpdate(['update_id' => 24002, 'message' => $reply]);

        $this->assertSame(
            1,
            $conversation->messages()->where('source', ConversationMessage::SOURCE_TELEGRAM)->count(),
        );
    }

    public function test_overlong_telegram_reply_is_rejected(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation] = $this->linkedConversationWithMapping(4500);

        $this->postUpdate([
            'update_id' => 25001,
            'message' => [
                'message_id' => 950,
                'text' => str_repeat('a', 4001),
                'chat' => ['id' => 4500],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        $this->assertSame(
            0,
            $conversation->messages()->where('source', ConversationMessage::SOURCE_TELEGRAM)->count(),
        );
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'too long'));
    }

    public function test_empty_telegram_reply_is_not_stored(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation] = $this->linkedConversationWithMapping(4600);

        $this->postUpdate([
            'update_id' => 26001,
            'message' => [
                'message_id' => 960,
                'text' => '   ',
                'chat' => ['id' => 4600],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        $this->assertSame(
            0,
            $conversation->messages()->where('source', ConversationMessage::SOURCE_TELEGRAM)->count(),
        );
    }

    public function test_telegram_reply_is_exposed_through_the_messages_api(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4700);

        $this->postUpdate([
            'update_id' => 27001,
            'message' => [
                'message_id' => 970,
                'text' => 'On my way.',
                'chat' => ['id' => 4700],
                'reply_to_message' => ['message_id' => 555],
            ],
        ]);

        Sanctum::actingAs($nurse);

        $messages = $this->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data');

        $reply = collect($messages)->firstWhere('source', ConversationMessage::SOURCE_TELEGRAM);

        $this->assertNotNull($reply);
        $this->assertSame('On my way.', $reply['body']);
    }

    public function test_outbound_telegram_delivery_records_the_message_mapping(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 777],
        ])]);

        $nurse = $this->userWithRole('Nurse');
        $conversation = Conversation::create([
            'type' => Conversation::TYPE_PRIVATE,
            'created_by' => $nurse->id,
        ]);
        $conversation->participants()->create(['user_id' => $nurse->id, 'joined_at' => now()]);

        $message = $conversation->messages()->create([
            'sender_id' => $nurse->id,
            'body' => 'Hello',
        ]);

        SendTelegramMessage::dispatch(
            chatId: 4242,
            text: 'Hello',
            userId: $nurse->id,
            eventType: 'new_message',
            idempotencyKey: 'mapping-test-1',
            conversationId: $conversation->id,
            conversationMessageId: $message->id,
        );

        $this->assertDatabaseHas('telegram_delivery_logs', [
            'telegram_chat_id' => 4242,
            'telegram_message_id' => 777,
            'conversation_id' => $conversation->id,
            'conversation_message_id' => $message->id,
            'status' => TelegramDeliveryLog::STATUS_SENT,
        ]);
    }

    public function test_chats_command_lists_authorized_conversations(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation] = $this->linkedConversationWithMapping(4800);

        $this->postUpdate(['update_id' => 28001, 'message' => [
            'message_id' => 980,
            'text' => '/chats',
            'chat' => ['id' => 4800],
        ]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], '#'.$conversation->id));
    }

    public function test_chats_command_only_lists_conversations_the_user_belongs_to(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $mine] = $this->linkedConversationWithMapping(4801);

        $doctor = $this->userWithRole('Doctor');
        $pharmacist = $this->userWithRole('Pharmacist');
        $foreign = Conversation::create(['type' => Conversation::TYPE_PRIVATE, 'created_by' => $doctor->id]);
        $foreign->participants()->create(['user_id' => $doctor->id, 'joined_at' => now()]);
        $foreign->participants()->create(['user_id' => $pharmacist->id, 'joined_at' => now()]);

        $this->postUpdate(['update_id' => 28002, 'message' => [
            'message_id' => 981,
            'text' => '/chats',
            'chat' => ['id' => 4801],
        ]]);

        Http::assertSent(function ($request) use ($mine, $foreign) {
            $text = (string) $request['text'];

            return str_contains($request->url(), 'sendMessage')
                && str_contains($text, '#'.$mine->id)
                && ! str_contains($text, '#'.$foreign->id);
        });
    }

    public function test_open_then_free_text_sends_to_the_selected_conversation(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4802);

        $this->postUpdate(['update_id' => 28003, 'message' => [
            'message_id' => 982,
            'text' => '/open '.$conversation->id,
            'chat' => ['id' => 4802],
        ]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'now messaging'));

        $this->assertDatabaseHas('telegram_connections', [
            'telegram_chat_id' => 4802,
            'active_conversation_id' => $conversation->id,
        ]);

        $this->postUpdate(['update_id' => 28004, 'message' => [
            'message_id' => 983,
            'text' => 'Hello from the train.',
            'chat' => ['id' => 4802],
        ]]);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $nurse->id,
            'body' => 'Hello from the train.',
            'source' => ConversationMessage::SOURCE_TELEGRAM,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'message was sent'));
    }

    public function test_open_rejects_a_conversation_the_user_is_not_part_of(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse] = $this->linkedConversationWithMapping(4803);

        $doctor = $this->userWithRole('Doctor');
        $pharmacist = $this->userWithRole('Pharmacist');
        $foreign = Conversation::create(['type' => Conversation::TYPE_PRIVATE, 'created_by' => $doctor->id]);
        $foreign->participants()->create(['user_id' => $doctor->id, 'joined_at' => now()]);
        $foreign->participants()->create(['user_id' => $pharmacist->id, 'joined_at' => now()]);

        $this->postUpdate(['update_id' => 28005, 'message' => [
            'message_id' => 984,
            'text' => '/open '.$foreign->id,
            'chat' => ['id' => 4803],
        ]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'could not find'));

        $this->assertDatabaseHas('telegram_connections', [
            'telegram_chat_id' => 4803,
            'active_conversation_id' => null,
        ]);
    }

    public function test_open_with_a_non_numeric_argument_shows_usage(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->linkedConversationWithMapping(4804);

        $this->postUpdate(['update_id' => 28006, 'message' => [
            'message_id' => 985,
            'text' => '/open not-a-number',
            'chat' => ['id' => 4804],
        ]]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'Usage: /open'));
    }

    public function test_close_clears_the_selected_conversation(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4805);

        $this->postUpdate(['update_id' => 28007, 'message' => [
            'message_id' => 986,
            'text' => '/open '.$conversation->id,
            'chat' => ['id' => 4805],
        ]]);

        $this->postUpdate(['update_id' => 28008, 'message' => [
            'message_id' => 987,
            'text' => '/close',
            'chat' => ['id' => 4805],
        ]]);

        $this->assertDatabaseHas('telegram_connections', [
            'telegram_chat_id' => 4805,
            'active_conversation_id' => null,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'no longer messaging'));

        // A message after closing no longer lands in the conversation.
        $countBefore = ConversationMessage::where('conversation_id', $conversation->id)->count();

        $this->postUpdate(['update_id' => 28009, 'message' => [
            'message_id' => 988,
            'text' => 'Anyone there?',
            'chat' => ['id' => 4805],
        ]]);

        $this->assertSame($countBefore, ConversationMessage::where('conversation_id', $conversation->id)->count());
    }

    public function test_explicit_open_takes_effect_only_for_that_chat(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation] = $this->linkedConversationWithMapping(4806);

        $this->postUpdate(['update_id' => 28010, 'message' => [
            'message_id' => 989,
            'text' => '/open '.$conversation->id,
            'chat' => ['id' => 4806],
        ]]);

        $this->assertDatabaseHas('telegram_connections', [
            'telegram_chat_id' => 4806,
            'active_conversation_id' => $conversation->id,
        ]);
        $this->assertDatabaseCount('telegram_connections', 1);
    }

    public function test_delivery_status_endpoint_lists_telegram_delivery_logs(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation, $message] = $this->linkedConversationWithMapping(4807);

        Sanctum::actingAs($nurse);

        $response = $this->getJson(
            "/api/v1/conversations/{$conversation->id}/messages/{$message->id}/delivery",
        )->assertOk();

        $this->assertSame('sent', $response->json('data.0.status'));
        $this->assertSame($nurse->id, $response->json('data.0.recipient_user_id'));
        $this->assertSame('telegram', $response->json('data.0.channel'));
        $this->assertArrayNotHasKey('telegram_chat_id', $response->json('data.0'));
    }

    public function test_delivery_status_endpoint_rejects_a_message_from_another_conversation(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [$nurse, $conversation] = $this->linkedConversationWithMapping(4808);

        $other = Conversation::create(['type' => Conversation::TYPE_PRIVATE, 'created_by' => $nurse->id]);
        $other->participants()->create(['user_id' => $nurse->id, 'joined_at' => now()]);
        $foreignMessage = $other->messages()->create(['sender_id' => $nurse->id, 'body' => 'Elsewhere']);

        Sanctum::actingAs($nurse);

        $this->getJson(
            "/api/v1/conversations/{$conversation->id}/messages/{$foreignMessage->id}/delivery",
        )->assertNotFound();
    }

    public function test_delivery_status_endpoint_requires_view_access(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        [, $conversation, $message] = $this->linkedConversationWithMapping(4809);

        $outsider = $this->userWithRole('Nurse');
        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/v1/conversations/{$conversation->id}/messages/{$message->id}/delivery",
        )->assertForbidden();
    }

    public function test_opening_a_conversation_does_not_require_a_reply(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $nurse = $this->userWithRole('Nurse');
        $doctor = $this->userWithRole('Doctor');

        TelegramConnection::create([
            'user_id' => $nurse->id,
            'telegram_chat_id' => 4810,
            'verified_at' => now(),
        ]);

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE, 'created_by' => $doctor->id]);
        $conversation->participants()->create(['user_id' => $nurse->id, 'joined_at' => now()]);
        $conversation->participants()->create(['user_id' => $doctor->id, 'joined_at' => now()]);

        $this->postUpdate(['update_id' => 28020, 'message' => [
            'message_id' => 990,
            'text' => '/open '.$conversation->id,
            'chat' => ['id' => 4810],
        ]]);

        $this->postUpdate(['update_id' => 28021, 'message' => [
            'message_id' => 991,
            'text' => 'Direct message without replying.',
            'chat' => ['id' => 4810],
        ]]);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $nurse->id,
            'body' => 'Direct message without replying.',
            'source' => ConversationMessage::SOURCE_TELEGRAM,
        ]);
    }
}
