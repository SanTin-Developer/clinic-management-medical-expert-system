<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\Symptom;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_create_and_list_symptoms(): void
    {
        $this->actingAsRole('Admin');

        $this->postJson('/api/v1/knowledge-base/symptoms', [
            'code' => 'SYM-TEST-COUGH',
            'name' => 'Test cough',
            'name_km' => 'ក្អក',
        ])->assertCreated()->assertJsonPath('data.code', 'SYM-TEST-COUGH');

        $this->getJson('/api/v1/knowledge-base/symptoms')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_admin_can_create_a_disease_with_symptoms(): void
    {
        $this->actingAsRole('Admin');

        $symptom = Symptom::create(['code' => 'SYM-A', 'name' => 'Symptom A']);

        $this->postJson('/api/v1/knowledge-base/diseases', [
            'code' => 'DIS-TEST',
            'name' => 'Test disease',
            'severity_level' => 'mild',
            'symptom_ids' => [$symptom->id],
        ])->assertCreated()
            ->assertJsonCount(1, 'data.symptoms');

        $this->assertDatabaseHas('disease_symptom', [
            'disease_id' => Disease::where('code', 'DIS-TEST')->first()->id,
            'symptom_id' => $symptom->id,
        ]);
    }

    public function test_disease_index_reports_category_stats(): void
    {
        $this->actingAsRole('Admin');

        Disease::create(['code' => 'DIS-CAT-R1', 'name' => 'Resp A', 'category' => 'respiratory']);
        Disease::create(['code' => 'DIS-CAT-R2', 'name' => 'Resp B', 'category' => 'respiratory']);
        Disease::create(['code' => 'DIS-CAT-C1', 'name' => 'Cardio A', 'category' => 'cardio']);

        $this->getJson('/api/v1/knowledge-base/diseases')
            ->assertOk()
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.by_category.respiratory', 2)
            ->assertJsonPath('stats.by_category.cardio', 1);
    }

    public function test_admin_can_update_a_disease(): void
    {
        $this->actingAsRole('Admin');

        $disease = Disease::create(['code' => 'DIS-UPD', 'name' => 'Old name']);

        $this->putJson("/api/v1/knowledge-base/diseases/{$disease->id}", [
            'name' => 'New name',
        ])->assertOk()->assertJsonPath('data.name', 'New name');
    }

    public function test_admin_can_create_an_expert_rule_with_conditions(): void
    {
        $this->actingAsRole('Admin');

        $disease = Disease::create(['code' => 'DIS-RULE', 'name' => 'Rule disease']);
        $symptom = Symptom::create(['code' => 'SYM-RULE', 'name' => 'Rule symptom']);

        $this->postJson('/api/v1/knowledge-base/expert-rules', [
            'code' => 'RULE-TEST',
            'name' => 'Test rule',
            'disease_id' => $disease->id,
            'priority' => 5,
            'confidence_score' => 0.7,
            'conditions' => [
                ['symptom_id' => $symptom->id, 'operator' => 'present', 'condition_group' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'RULE-TEST')
            ->assertJsonCount(1, 'data.conditions');
    }

    public function test_expert_rule_rejects_duplicate_conditions_in_same_group(): void
    {
        $this->actingAsRole('Admin');

        $disease = Disease::create(['code' => 'DIS-DUP', 'name' => 'Dup disease']);
        $symptom = Symptom::create(['code' => 'SYM-DUP', 'name' => 'Dup symptom']);

        $this->postJson('/api/v1/knowledge-base/expert-rules', [
            'code' => 'RULE-DUP',
            'name' => 'Duplicate rule',
            'disease_id' => $disease->id,
            'conditions' => [
                ['symptom_id' => $symptom->id, 'condition_group' => 1],
                ['symptom_id' => $symptom->id, 'condition_group' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_disease_with_rules_cannot_be_deleted(): void
    {
        $this->actingAsRole('Admin');

        $disease = Disease::create(['code' => 'DIS-KEEP', 'name' => 'Keep disease']);
        $symptom = Symptom::create(['code' => 'SYM-KEEP', 'name' => 'Keep symptom']);

        $this->postJson('/api/v1/knowledge-base/expert-rules', [
            'code' => 'RULE-KEEP',
            'name' => 'Keep rule',
            'disease_id' => $disease->id,
            'conditions' => [['symptom_id' => $symptom->id]],
        ])->assertCreated();

        $this->deleteJson("/api/v1/knowledge-base/diseases/{$disease->id}")
            ->assertStatus(422);
    }

    public function test_roles_without_knowledge_base_permission_are_forbidden(): void
    {
        $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/knowledge-base/diseases')->assertForbidden();
        $this->getJson('/api/v1/knowledge-base/symptoms')->assertForbidden();
        $this->getJson('/api/v1/knowledge-base/expert-rules')->assertForbidden();
    }

    public function test_doctor_can_view_but_not_manage_the_knowledge_base(): void
    {
        $this->actingAsRole('Doctor');

        $this->getJson('/api/v1/knowledge-base/diseases')->assertOk();
        $this->getJson('/api/v1/knowledge-base/symptoms')->assertOk();
        $this->getJson('/api/v1/knowledge-base/expert-rules')->assertOk();

        $this->postJson('/api/v1/knowledge-base/symptoms', [
            'code' => 'SYM-DOCTOR',
            'name' => 'Doctor symptom',
        ])->assertForbidden();
    }

    public function test_admin_can_create_a_symptom_without_a_code(): void
    {
        $this->actingAsRole('Admin');

        $response = $this->postJson('/api/v1/knowledge-base/symptoms', [
            'name' => 'Auto-coded symptom',
        ])->assertCreated();

        $this->assertStringStartsWith('SYM-', $response->json('data.code'));
        $this->assertDatabaseHas('symptoms', ['name' => 'Auto-coded symptom']);
    }

    public function test_admin_can_create_a_disease_without_a_code(): void
    {
        $this->actingAsRole('Admin');

        $response = $this->postJson('/api/v1/knowledge-base/diseases', [
            'name' => 'Auto-coded disease',
        ])->assertCreated();

        $this->assertStringStartsWith('DIS-', $response->json('data.code'));
        $this->assertDatabaseHas('diseases', ['name' => 'Auto-coded disease']);
    }

    public function test_admin_can_create_an_expert_rule_without_a_code(): void
    {
        $this->actingAsRole('Admin');

        $disease = Disease::create(['code' => 'DIS-AUTO', 'name' => 'Auto disease']);
        $symptom = Symptom::create(['code' => 'SYM-AUTO', 'name' => 'Auto symptom']);

        $response = $this->postJson('/api/v1/knowledge-base/expert-rules', [
            'name' => 'Auto-coded rule',
            'disease_id' => $disease->id,
            'conditions' => [
                ['symptom_id' => $symptom->id, 'condition_group' => 1],
            ],
        ])->assertCreated();

        $this->assertStringStartsWith('RULE-', $response->json('data.code'));
        $this->assertDatabaseHas('expert_rules', ['name' => 'Auto-coded rule']);
    }
}
