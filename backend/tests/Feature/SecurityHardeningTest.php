<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use App\Services\Auth\SecurityEventService;
use App\Services\Auth\TotpService;
use App\Services\Auth\TwoFactorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
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

    /**
     * @return array{0: User, 1: string}
     */
    private function adminWithTotp(): array
    {
        $secret = $this->totp()->generateSecret();

        $user = User::factory()->withTwoFactor($secret)->create();
        $user->assignRole('Admin');

        return [$user, $secret];
    }

    public function test_step_up_is_password_only_by_default_even_with_totp_enrolled(): void
    {
        [$user] = $this->adminWithTotp();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['step_up_token']);
    }

    public function test_step_up_requires_a_totp_code_when_enforced(): void
    {
        Config::set('auth.step_up.require_two_factor', true);
        [$user] = $this->adminWithTotp();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('two_factor_required', true);
    }

    public function test_step_up_accepts_a_valid_totp_code_when_enforced(): void
    {
        Config::set('auth.step_up.require_two_factor', true);
        [$user, $secret] = $this->adminWithTotp();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
            'two_factor_code' => $this->totp()->currentCode($secret),
        ])->assertOk()->assertJsonStructure(['step_up_token']);
    }

    public function test_step_up_accepts_a_recovery_code_when_enforced(): void
    {
        Config::set('auth.step_up.require_two_factor', true);
        [$user] = $this->adminWithTotp();
        Sanctum::actingAs($user);

        $codes = app(TwoFactorService::class)->regenerateRecoveryCodes($user);

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
            'two_factor_code' => $codes[0],
        ])->assertOk()->assertJsonStructure(['step_up_token']);
    }

    public function test_step_up_rejects_an_incorrect_totp_code_when_enforced(): void
    {
        Config::set('auth.step_up.require_two_factor', true);
        [$user] = $this->adminWithTotp();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
            'two_factor_code' => '000000',
        ])->assertStatus(422);
    }

    public function test_step_up_does_not_require_totp_for_accounts_without_it(): void
    {
        Config::set('auth.step_up.require_two_factor', true);
        $user = $this->actingAsRole('Admin');

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['step_up_token']);
    }

    public function test_security_events_expose_a_risk_score(): void
    {
        $user = $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->getJson('/api/v1/auth/security-events')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'event', 'severity', 'risk_score', 'created_at']]]);
    }

    public function test_a_critical_security_event_notifies_the_account_owner(): void
    {
        Notification::fake();
        Config::set('auth.security_alerts.enabled', true);

        $user = $this->userWithRole('Doctor');

        $request = Request::create('/api/v1/auth/login', 'POST');

        app(SecurityEventService::class)->record(
            SecurityEventService::LOGIN_LOCKED,
            $user,
            'critical',
            [],
            $request,
        );

        Notification::assertSentTo($user, SecurityAlertNotification::class);
    }

    public function test_non_critical_events_do_not_notify(): void
    {
        Notification::fake();
        Config::set('auth.security_alerts.enabled', true);

        $user = $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'wrong',
        ])->assertStatus(422);

        Notification::assertNotSentTo($user, SecurityAlertNotification::class);
    }
}
