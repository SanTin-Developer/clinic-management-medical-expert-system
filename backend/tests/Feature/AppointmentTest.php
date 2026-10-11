<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function payload(Patient $patient, int $doctorId, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => '2030-01-01',
            'appointment_time' => '10:00',
            'reason' => 'Checkup',
        ], $overrides);
    }

    public function test_receptionist_can_schedule_an_appointment(): void
    {
        $this->actingAsRole('Receptionist');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/appointments', $this->payload($patient, $doctor->id))
            ->assertCreated()
            ->assertJsonPath('appointment.status', 'scheduled');
    }

    public function test_doctor_cannot_schedule_an_appointment(): void
    {
        $this->actingAsRole('Doctor');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/appointments', $this->payload($patient, $doctor->id))
            ->assertForbidden();
    }

    public function test_double_booking_the_same_doctor_slot_is_rejected(): void
    {
        $this->actingAsRole('Receptionist');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/appointments', $this->payload($patient, $doctor->id))
            ->assertCreated();

        $this->postJson('/api/v1/appointments', $this->payload($patient, $doctor->id))
            ->assertStatus(422);
    }

    public function test_receptionist_can_cancel_an_appointment(): void
    {
        $this->actingAsRole('Receptionist');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        $appointment = Appointment::create([
            'appointment_number' => 'APT-000001',
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => '2030-02-01',
            'appointment_time' => '11:00',
            'status' => 'scheduled',
        ]);

        $this->postJson("/api/v1/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'cancelled');
    }
}
