<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    public function test_resend_sends_a_verification_notification(): void
    {
        $user = $this->userWithRole('Doctor');
        $user->forceFill(['email_verified_at' => null])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_a_no_op_for_verified_users(): void
    {
        $user = $this->userWithRole('Doctor');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email address already verified.');

        Notification::assertNothingSent();
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url.'&extra=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_middleware_blocks_unverified_users(): void
    {
        Route::middleware(['auth:sanctum', 'verified'])
            ->get('/api/v1/_test/verified', fn () => response()->json(['ok' => true]));

        $unverified = $this->userWithRole('Doctor');
        $unverified->forceFill(['email_verified_at' => null])->save();

        Sanctum::actingAs($unverified);
        $this->getJson('/api/v1/_test/verified')->assertForbidden();

        $verified = $this->userWithRole('Doctor');
        Sanctum::actingAs($verified);
        $this->getJson('/api/v1/_test/verified')->assertOk();
    }
}
