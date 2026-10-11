<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config()->set('auth.otp.enabled', true);
        Notification::fake();
    }

    public function test_login_from_new_device_requires_otp(): void
    {
        $user = $this->userWithRole('Doctor');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('otp_required', true)
            ->assertJsonStructure(['challenge_token', 'expires_in']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertSentTo($user, LoginOtpNotification::class);
    }

    public function test_otp_verification_issues_token_and_trusts_device(): void
    {
        $user = $this->userWithRole('Doctor');

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('challenge_token');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_token' => $challengeToken,
            'code' => $this->capturedCode($user),
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('trusted_devices', 1);

        // With EncryptCookies on the API group, the response cookie is encrypted
        // at rest and must be round-tripped the same way a real client would.
        $cookie = $response->getCookie((string) config('auth.otp.cookie_name'));
        $this->assertNotNull($cookie);

        $this->withCredentials()
            ->withCookie((string) config('auth.otp.cookie_name'), $cookie->getValue())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_otp_verification_fails_with_incorrect_code(): void
    {
        $user = $this->userWithRole('Doctor');

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('challenge_token');

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_otp_challenge_locks_after_max_attempts(): void
    {
        config()->set('auth.otp.max_attempts', 2);

        $user = $this->userWithRole('Doctor');

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('challenge_token');

        $code = $this->capturedCode($user);

        $this->postJson('/api/v1/auth/otp/verify', ['challenge_token' => $challengeToken, 'code' => '000001'])
            ->assertStatus(422);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_token' => $challengeToken, 'code' => '000002'])
            ->assertStatus(422);

        // The correct code is rejected once the attempt limit is reached.
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_token' => $challengeToken, 'code' => $code])
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_list_and_revoke_devices(): void
    {
        $user = $this->userWithRole('Doctor');

        $device = $user->trustedDevices()->create([
            'device_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', 'token'),
            'name' => 'Test device',
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/auth/devices/{$device->id}")->assertOk();
        $this->assertDatabaseCount('trusted_devices', 0);
    }

    public function test_user_cannot_revoke_another_users_device(): void
    {
        $owner = $this->userWithRole('Doctor');
        $device = $owner->trustedDevices()->create([
            'device_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', 'token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAsRole('Nurse');

        $this->deleteJson("/api/v1/auth/devices/{$device->id}")->assertNotFound();
        $this->assertDatabaseCount('trusted_devices', 1);
    }

    private function capturedCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, LoginOtpNotification::class, function (LoginOtpNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return (string) $code;
    }
}
