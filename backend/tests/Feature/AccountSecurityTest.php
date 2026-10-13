<?php

namespace Tests\Feature;

use App\Notifications\EmailChangeCodeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_can_change_their_password_and_sessions_are_revoked(): void
    {
        $user = $this->userWithRole('Doctor');
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'Str0ng@Passw0rd',
                'password_confirmation' => 'Str0ng@Passw0rd',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('Str0ng@Passw0rd', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_change_rejects_the_wrong_current_password(): void
    {
        $this->actingAsRole('Doctor');

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong',
            'password' => 'Str0ng@Passw0rd',
            'password_confirmation' => 'Str0ng@Passw0rd',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_email_change_is_two_stage(): void
    {
        Notification::fake();

        $user = $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/email/change', [
            'new_email' => 'moved.doctor@clinic.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('new_email', 'moved.doctor@clinic.local');

        $code = null;

        Notification::assertSentOnDemand(
            EmailChangeCodeNotification::class,
            function (EmailChangeCodeNotification $notification, $channels, $notifiable) use (&$code) {
                $code = $notification->code;

                return $notifiable->routes['mail'] === 'moved.doctor@clinic.local';
            },
        );

        $this->assertNotNull($code);

        // the address is unchanged until the code is confirmed
        $this->assertNotSame('moved.doctor@clinic.local', $user->fresh()->email);

        $this->postJson('/api/v1/auth/email/change/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('email', 'moved.doctor@clinic.local');

        $this->assertSame('moved.doctor@clinic.local', $user->fresh()->email);
    }

    public function test_email_change_requires_the_correct_password(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/email/change', [
            'new_email' => 'moved.doctor@clinic.local',
            'password' => 'wrong',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_email_change_confirm_rejects_wrong_code(): void
    {
        Notification::fake();

        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/email/change', [
            'new_email' => 'moved.doctor@clinic.local',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/email/change/confirm', ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_step_up_rejects_a_wrong_password(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_user_can_list_their_security_events(): void
    {
        $this->actingAsRole('Doctor');

        // generate at least one event
        $this->postJson('/api/v1/auth/step-up', [
            'action' => 'users.suspend',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->getJson('/api/v1/auth/security-events')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'event', 'severity', 'created_at']]]);
    }
}
