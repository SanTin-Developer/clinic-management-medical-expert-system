<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\TotpService;
use App\Services\Auth\TwoFactorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function totp(): TotpService
    {
        return app(TotpService::class);
    }

    private function userWithTotp(): array
    {
        $secret = $this->totp()->generateSecret();

        $user = User::factory()->withTwoFactor($secret)->create();
        $user->assignRole('Doctor');

        return [$user, $secret];
    }

    public function test_user_can_enroll_and_confirm_two_factor(): void
    {
        $user = $this->actingAsRole('Doctor');

        $enroll = $this->postJson('/api/v1/auth/2fa/enroll', ['password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url']);

        $secret = $enroll->json('secret');
        $code = $this->totp()->currentCode($secret);

        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonCount(8, 'recovery_codes');

        $this->getJson('/api/v1/auth/2fa/status')
            ->assertOk()
            ->assertJsonPath('enabled', true);
    }

    public function test_enroll_requires_the_account_password(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/2fa/enroll', ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_confirm_rejects_an_incorrect_code(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/2fa/enroll', ['password' => 'password'])->assertOk();

        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_login_requires_two_factor_when_mandatory(): void
    {
        Config::set('auth.two_factor.mandatory', true);

        [$user, $secret] = $this->userWithTotp();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(202)
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['challenge_token']);

        $code = $this->totp()->currentCode($secret);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.two_factor_enabled', true);
    }

    public function test_login_without_enrolled_two_factor_is_unaffected_when_mandatory(): void
    {
        Config::set('auth.two_factor.mandatory', true);

        $user = $this->userWithRole('Nurse');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_two_factor_challenge_rejects_incorrect_code(): void
    {
        Config::set('auth.two_factor.mandatory', true);

        [$user] = $this->userWithTotp();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_a_totp_code_cannot_be_replayed(): void
    {
        Config::set('auth.two_factor.mandatory', true);

        [$user, $secret] = $this->userWithTotp();

        $code = $this->totp()->currentCode($secret);

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $first->json('challenge_token'),
            'code' => $code,
        ])->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $second->json('challenge_token'),
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_a_recovery_code_can_be_used_once(): void
    {
        Config::set('auth.two_factor.mandatory', true);

        [$user] = $this->userWithTotp();
        $codes = app(TwoFactorService::class)->regenerateRecoveryCodes($user);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => $codes[0],
        ])->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $second->json('challenge_token'),
            'code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_user_can_disable_two_factor(): void
    {
        [$user] = $this->userWithTotp();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'password'])
            ->assertOk();

        $user->refresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertDatabaseCount('two_factor_recovery_codes', 0);
    }

    public function test_disable_requires_the_account_password(): void
    {
        [$user] = $this->userWithTotp();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
