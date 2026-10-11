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

class ConsultationTest extends TestCase
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
            'appointment_number' => 'APT-CON-'.random_int(100000, 999999),
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => '2030-04-01',
            'appointment_time' => '08:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_doctor_can_create_a_consultation_for_their_appointment(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();
        $appointment = $this->appointmentFor($patient, $doctor->id);

        $this->postJson('/api/v1/consultations', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Headache',
        ])->assertCreated()->assertJsonPath('data.chief_complaint', 'Headache');
    }

    public function test_only_one_consultation_is_allowed_per_appointment(): void
    {
        $doctor = $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();
        $appointment = $this->appointmentFor($patient, $doctor->id);

        Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->postJson('/api/v1/consultations', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ])->assertStatus(422);
    }

    public function test_nurse_cannot_create_a_consultation(): void
    {
        $this->actingAsRole('Nurse');

        $this->postJson('/api/v1/consultations', [
            'appointment_id' => 1,
            'patient_id' => 1,
            'doctor_id' => 1,
        ])->assertForbidden();
    }
}
