<?php

namespace Tests\Feature;

use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'gender' => 'male',
            'date_of_birth' => '1990-05-15',
            'phone_number' => '0123456789',
        ], $overrides);
    }

    public function test_receptionist_can_create_a_patient(): void
    {
        $this->actingAsRole('Receptionist');

        $this->postJson('/api/v1/patients', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'John');

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_receptionist_can_list_patients(): void
    {
        $this->actingAsRole('Receptionist');
        Patient::factory()->count(2)->create();

        $this->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_doctor_cannot_create_a_patient(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/patients', $this->validPayload())
            ->assertForbidden();
    }

    public function test_patient_creation_validates_required_fields(): void
    {
        $this->actingAsRole('Receptionist');

        $this->postJson('/api/v1/patients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'gender', 'date_of_birth']);
    }
}
