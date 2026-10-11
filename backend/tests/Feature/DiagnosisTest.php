<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DiagnosisTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function consultationFor(int $doctorId): Consultation
    {
        $patient = Patient::factory()->create();

        $appointment = Appointment::create([
            'appointment_number' => 'APT-DIA-'.random_int(100000, 999999),
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => '2030-05-01',
            'appointment_time' => '14:00',
            'status' => 'scheduled',
        ]);

        return Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
        ]);
    }

    public function test_doctor_can_diagnose_their_own_consultation(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $consultation = $this->consultationFor($doctor->id);

        $this->postJson('/api/v1/diagnoses', [
            'consultation_id' => $consultation->id,
            'diagnosis_name' => 'Influenza',
            'severity' => 'moderate',
        ])->assertCreated()->assertJsonPath('data.diagnosis_name', 'Influenza');
    }

    public function test_doctor_cannot_diagnose_another_doctors_consultation(): void
    {
        $owningDoctor = $this->userWithRole('Doctor');
        $this->actingAsRole('Doctor');
        $consultation = $this->consultationFor($owningDoctor->id);

        $this->postJson('/api/v1/diagnoses', [
            'consultation_id' => $consultation->id,
            'diagnosis_name' => 'Influenza',
        ])->assertForbidden();
    }

    public function test_nurse_cannot_create_a_diagnosis(): void
    {
        $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/diagnoses', [
            'consultation_id' => 1,
            'diagnosis_name' => 'Influenza',
        ])->assertForbidden();
    }
}
