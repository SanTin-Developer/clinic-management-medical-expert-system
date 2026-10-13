<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetOtpNotification;
use Database\Seeders\RolePermissionSeeder;
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

    public function test_forgot_password_response_is_generic_and_sends_code(): void
    {
        $user = $this->userWithRole('Doctor');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email address, a password reset code has been sent.');

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
        $this->assertDatabaseCount('password_reset_otps', 1);
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@clinic.local'])
            ->assertOk();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_verify_code_issues_a_reset_token(): void
    {
        $user = $this->userWithRole('Doctor');
        $code = $this->captureResetCode($user);

        $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()
            ->assertJsonStructure(['message', 'reset_token']);

        $this->assertDatabaseHas('password_reset_otps', [
            'user_id' => $user->id,
            'consumed_at' => null,
        ]);
    }

    public function test_password_can_be_reset_with_verified_token(): void
    {
        $user = $this->userWithRole('Doctor');
        $user->createToken('existing-session');
        $user->trustedDevices()->create([
            'device_id' => 'device-1',
            'token_hash' => hash('sha256', 'token'),
            'expires_at' => now()->addDay(),
        ]);

        $resetToken = $this->captureResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecret@123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('trusted_devices', 0);
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = $this->userWithRole('Doctor');
        $resetToken = $this->captureResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'AnotherSecret@123',
            'password_confirmation' => 'AnotherSecret@123',
        ])->assertStatus(422)->assertJsonValidationErrors('reset_token');
    }

    public function test_code_is_consumed_by_a_completed_reset(): void
    {
        $user = $this->userWithRole('Doctor');
        $code = $this->captureResetCode($user);
        $resetToken = $this->verifyCode($user, $code);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_verify_code_fails_with_invalid_code(): void
    {
        $user = $this->userWithRole('Doctor');
        $this->captureResetCode($user);

        $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_verify_code_does_not_reveal_unknown_accounts(): void
    {
        $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => 'nobody@clinic.local',
            'code' => '123456',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_verify_code_fails_with_expired_code(): void
    {
        $user = $this->userWithRole('Doctor');
        $this->captureResetCode($user);

        $user->passwordResetOtps()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_password_reset_requires_confirmation_and_min_length(): void
    {
        $user = $this->userWithRole('Doctor');
        $resetToken = $this->captureResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_password_reset_rejects_passwords_missing_complexity(): void
    {
        $user = $this->userWithRole('Doctor');
        $resetToken = $this->captureResetToken($user);

        // Long enough with mixed case and numbers, but no special character.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NoSymbol123',
            'password_confirmation' => 'NoSymbol123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        // Has numbers and a symbol, but no uppercase letter.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'nosymbol@123',
            'password_confirmation' => 'nosymbol@123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    private function captureResetCode(User $user): string
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;

        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function (PasswordResetOtpNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return (string) $code;
    }

    private function verifyCode(User $user, string $code): string
    {
        return (string) $this->postJson('/api/v1/auth/reset-password/verify-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()->json('reset_token');
    }

    private function captureResetToken(User $user): string
    {
        return $this->verifyCode($user, $this->captureResetCode($user));
    }
}