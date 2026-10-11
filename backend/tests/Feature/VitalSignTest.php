<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class VitalSignTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function appointmentFor(Patient $patient, int $doctorId): Appointment
    {
        return Appointment::create([
            'appointment_number' => 'APT-VIT-'.random_int(100000, 999999),
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => '2030-03-01',
            'appointment_time' => '09:30',
            'status' => 'scheduled',
        ]);
    }

    public function test_nurse_can_record_vital_signs(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();
        $appointment = $this->appointmentFor($patient, $doctor->id);

        $this->postJson('/api/v1/vital-signs', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'nurse_id' => $nurse->id,
            'temperature' => 37.2,
            'heart_rate' => 80,
        ])->assertCreated()->assertJsonPath('data.heart_rate', 80);

        $this->assertDatabaseCount('vital_signs', 1);
    }

    public function test_patient_must_match_the_appointment(): void
    {
        $nurse = $this->actingAsRole('Nurse');
        $doctor = $this->userWithRole('Doctor');
        $appointment = $this->appointmentFor(Patient::factory()->create(), $doctor->id);
        $otherPatient = Patient::factory()->create();

        $this->postJson('/api/v1/vital-signs', [
            'appointment_id' => $appointment->id,
            'patient_id' => $otherPatient->id,
            'nurse_id' => $nurse->id,
        ])->assertStatus(422);
    }

    public function test_receptionist_cannot_record_vital_signs(): void
    {
        $this->actingAsRole('Receptionist');

        $this->postJson('/api/v1/vital-signs', [
            'appointment_id' => 1,
            'patient_id' => 1,
            'nurse_id' => 1,
        ])->assertForbidden();
    }
}
