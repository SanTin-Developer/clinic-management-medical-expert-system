<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    public function test_forgot_password_response_is_generic_and_sends_link(): void
    {
        $user = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email address, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@clinic.local'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->userWithRole('Doctor');
        $user->createToken('existing-session');
        $user->trustedDevices()->create([
            'device_id' => 'device-1',
            'token_hash' => hash('sha256', 'token'),
            'expires_at' => now()->addDay(),
        ]);

        $token = $this->captureResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('trusted_devices', 0);
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_password_reset_requires_confirmation_and_min_length(): void
    {
        $user = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'whatever',
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    private function captureResetToken(User $user): string
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        return (string) $token;
    }
}
