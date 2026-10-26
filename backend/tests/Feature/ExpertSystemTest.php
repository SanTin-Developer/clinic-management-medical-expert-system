<?php

namespace Tests\Feature;

use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpertSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function symptom(string $code, bool $active = true): Symptom
    {
        return Symptom::create([
            'code' => $code,
            'name' => $code,
            'description' => null,
            'is_active' => $active,
        ]);
    }

    private function pythonPayload(array $overrides = []): array
    {
        return array_merge([
            'engine' => 'rule-based-expert-system',
            'engine_version' => '1.0.0',
            'is_clinically_validated' => false,
            'matched_rule_count' => 1,
            'matched_rules' => [[
                'rule_code' => 'RULE-AND',
                'rule_name' => 'AND rule',
                'rule_name_km' => 'ច្បាប់ AND',
                'disease_code' => 'DIS-A',
                'disease_name' => 'Illness A',
                'disease_name_km' => 'ជំងឺ អេ',
                'priority' => 5,
                'confidence_score' => 0.5,
                'severity_level' => 'mild',
                'matched_condition_groups' => [1],
                'explanation' => null,
                'explanation_km' => 'ការពន្យល់ជាភាសាខ្មែរ។',
            ]],
            'notice' => 'Not clinically validated.',
        ], $overrides);
    }

    public function test_doctor_with_permission_can_evaluate(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);

        $fever = $this->symptom('SYM-FEVER');
        $cough = $this->symptom('SYM-COUGH');
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $response = $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [$fever->id, $cough->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Expert system evaluation completed.')
            ->assertJsonPath('data.matched_rule_count', 1)
            ->assertJsonPath('data.is_clinically_validated', false)
            ->assertJsonPath('data.matched_rules.0.rule_name_km', 'ច្បាប់ AND')
            ->assertJsonPath('data.matched_rules.0.disease_name_km', 'ជំងឺ អេ')
            ->assertJsonPath('data.matched_rules.0.explanation_km', 'ការពន្យល់ជាភាសាខ្មែរ។');

        Http::assertSent(function ($request) use ($fever, $cough) {
            return str_ends_with($request->url(), '/evaluate')
                && $request['symptom_ids'] === [$fever->id, $cough->id];
        });
    }

    public function test_authorized_roles_without_permission_are_forbidden(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);
        $fever = $this->symptom('SYM-FEVER');

        foreach (['Nurse', 'Receptionist', 'Patient', 'Pharmacist'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));

            $this->postJson('/api/v1/expert-system/evaluate', [
                'symptom_ids' => [$fever->id],
            ])->assertForbidden();
        }

        Http::assertNothingSent();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);

        $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [1],
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_empty_symptom_list_is_rejected_before_forwarding(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [],
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_nonexistent_symptom_is_rejected_before_forwarding(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [999999],
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_inactive_symptom_is_rejected_before_forwarding(): void
    {
        Http::fake(['*' => Http::response($this->pythonPayload(), 200)]);
        $inactive = $this->symptom('SYM-RETIRED', false);
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [$inactive->id],
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_connection_failure_returns_service_unavailable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $fever = $this->symptom('SYM-FEVER');
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $response = $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [$fever->id],
        ]);

        $response->assertStatus(503);
        $this->assertStringNotContainsString('cURL', $response->getContent());
        $this->assertStringNotContainsString('timed out', $response->getContent());
    }

    public function test_invalid_upstream_response_returns_bad_gateway(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => true], 200)]);

        $fever = $this->symptom('SYM-FEVER');
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [$fever->id],
        ])->assertStatus(502);
    }

    public function test_upstream_error_returns_bad_gateway_without_leaking_details(): void
    {
        Http::fake(['*' => Http::response('internal postgres stack trace', 500)]);

        $fever = $this->symptom('SYM-FEVER');
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $response = $this->postJson('/api/v1/expert-system/evaluate', [
            'symptom_ids' => [$fever->id],
        ]);

        $response->assertStatus(502);
        $this->assertStringNotContainsString('postgres', $response->getContent());
    }
}
