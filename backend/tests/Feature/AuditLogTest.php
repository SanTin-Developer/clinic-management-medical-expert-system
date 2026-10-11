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

    private function audit(): ExpertEvaluationAudit
    {
        return ExpertEvaluationAudit::create([
            'symptom_ids' => '1,2,3',
            'matched_rule_count' => 2,
            'matched_rule_codes' => 'RULE-A,RULE-B',
            'engine_version' => '1.0.0',
            'is_clinically_validated' => false,
        ]);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $this->actingAsRole('Admin');
        $this->audit();

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matched_rule_count', 2);
    }

    public function test_admin_can_view_a_single_audit_log(): void
    {
        $this->actingAsRole('Admin');
        $audit = $this->audit();

        $this->getJson("/api/v1/audit-logs/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.engine_version', '1.0.0');
    }

    public function test_roles_without_audit_permission_are_forbidden(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }
}
