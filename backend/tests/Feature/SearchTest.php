<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/search?q=abc')->assertUnauthorized();
    }

    public function test_query_is_required_and_has_a_minimum_length(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/search')->assertStatus(422);
        $this->getJson('/api/v1/search?q=a')->assertStatus(422);
    }

    public function test_search_returns_matching_patients_for_permitted_users(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create([
            'first_name' => 'Zephyr',
            'last_name' => 'Quimby',
            'patient_number' => 'PAT-777001',
        ]);

        $response = $this->getJson('/api/v1/search?q=Zephyr');

        $response->assertOk()
            ->assertJsonPath('query', 'Zephyr')
            ->assertJsonPath('groups.0.type', 'patients')
            ->assertJsonPath('groups.0.items.0.id', $patient->id)
            ->assertJsonPath('groups.0.items.0.url', "/patients/{$patient->id}");
    }

    public function test_search_finds_patients_by_number(): void
    {
        $this->actingAsRole('Receptionist');
        Patient::factory()->create(['patient_number' => 'PAT-909090']);

        $response = $this->getJson('/api/v1/search?q=PAT-909090');

        $response->assertOk()
            ->assertJsonPath('groups.0.type', 'patients')
            ->assertJsonCount(1, 'groups.0.items');
    }

    public function test_search_results_respect_permissions(): void
    {
        Patient::factory()->create(['first_name' => 'Hiddenname']);

        $user = User::factory()->create();
        $user->givePermissionTo('billing.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/search?q=Hiddenname');

        $response->assertOk();
        $types = array_column($response->json('groups'), 'type');

        $this->assertNotContains('patients', $types);
        $this->assertNotContains('appointments', $types);
        $this->assertNotContains('medicines', $types);
        $this->assertContains('invoices', $types);
    }

    public function test_search_limits_results_per_group(): void
    {
        $this->actingAsRole('Admin');

        Patient::factory()->count(8)->create(['last_name' => 'Findme']);

        $response = $this->getJson('/api/v1/search?q=Findme');

        $response->assertOk()
            ->assertJsonCount(5, 'groups.0.items');
    }

    public function test_search_finds_medicines_for_permitted_users(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('medicines.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        $medicine = Medicine::factory()->create(['name' => 'Paracetamolum', 'code' => 'MED-555111']);

        $response = $this->getJson('/api/v1/search?q=Paracetamolum');

        $response->assertOk()
            ->assertJsonPath('groups.0.type', 'medicines')
            ->assertJsonPath('groups.0.items.0.id', $medicine->id)
            ->assertJsonPath('groups.0.items.0.url', '/pharmacy');
    }
}
