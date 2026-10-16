<?php

namespace Tests\Feature;

use App\Models\ExpertEvaluationAudit;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function audit(array $attributes = []): ExpertEvaluationAudit
    {
        return ExpertEvaluationAudit::create(array_merge([
            'symptom_ids' => '1,2,3',
            'matched_rule_count' => 2,
            'matched_rule_codes' => 'RULE-A,RULE-B',
            'engine_version' => '1.0.0',
            'is_clinically_validated' => false,
        ], $attributes));
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $this->actingAsRole('Admin');
        $this->audit();

        $this->getJson('/api/v1/expert-system/audit-logs')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matched_rule_count', 2);
    }

    public function test_index_returns_audit_statistics(): void
    {
        $this->actingAsRole('Admin');
        $this->audit();
        $this->audit([
            'engine_version' => '2.0.0',
            'is_clinically_validated' => true,
        ]);

        $this->getJson('/api/v1/expert-system/audit-logs')
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.last_24h', 2)
            ->assertJsonPath('stats.validated', 1)
            ->assertJsonCount(2, 'stats.engine_versions');
    }

    public function test_index_filters_by_validation_flag(): void
    {
        $this->actingAsRole('Admin');
        $this->audit();
        $this->audit(['is_clinically_validated' => true]);

        $this->getJson('/api/v1/expert-system/audit-logs?validated=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.is_clinically_validated', true);
    }

    public function test_index_filters_by_validation_flag_sent_as_string(): void
    {
        $this->actingAsRole('Admin');
        $this->audit();
        $this->audit(['is_clinically_validated' => true]);

        $this->getJson('/api/v1/expert-system/audit-logs?validated=true')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.is_clinically_validated', true);

        $this->getJson('/api/v1/expert-system/audit-logs?validated=false')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.is_clinically_validated', false);
    }

    public function test_index_filters_by_engine_version(): void
    {
        $this->actingAsRole('Admin');
        $this->audit(['engine_version' => '1.0.0']);
        $this->audit(['engine_version' => '2.0.0']);

        $this->getJson('/api/v1/expert-system/audit-logs?engine_version=2.0.0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.engine_version', '2.0.0');
    }

    public function test_admin_can_view_a_single_audit_log(): void
    {
        $this->actingAsRole('Admin');
        $audit = $this->audit();

        $this->getJson("/api/v1/expert-system/audit-logs/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.engine_version', '1.0.0');
    }

    public function test_roles_without_audit_permission_are_forbidden(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/expert-system/audit-logs')->assertForbidden();
    }
}
