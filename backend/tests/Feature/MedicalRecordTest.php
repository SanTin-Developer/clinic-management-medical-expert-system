<?php

namespace Tests\Feature;

use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MedicalRecordTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_doctor_can_list_medical_records(): void
    {
        $this->actingAsRole('Doctor');

        $this->getJson('/api/v1/medical-records')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_nurse_can_list_medical_records(): void
    {
        $this->actingAsRole('Nurse');

        $this->getJson('/api/v1/medical-records')->assertOk();
    }

    public function test_doctor_can_create_a_medical_record(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/medical-records', [
            'patient_id' => $patient->id,
            'medical_history' => 'Hypertension',
            'treatment_plan' => 'Lifestyle changes',
        ])->assertCreated()->assertJsonPath('data.doctor_id', $doctor->id);
    }

    public function test_receptionist_cannot_list_medical_records(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/medical-records')->assertForbidden();
    }
}
