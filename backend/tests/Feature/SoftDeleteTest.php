<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function appointment(Patient $patient, int $doctorId): Appointment
    {
        return Appointment::create([
            'appointment_number' => 'APT-SD-'.random_int(100000, 999999),
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => '2030-05-01',
            'appointment_time' => '08:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_admin_can_soft_delete_a_patient(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create();

        $this->deleteJson("/api/v1/patients/{$patient->id}")->assertOk();

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_soft_deleted_patient_is_hidden_from_listing_and_show(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create();

        $this->deleteJson("/api/v1/patients/{$patient->id}")->assertOk();

        $this->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->getJson("/api/v1/patients/{$patient->id}")->assertNotFound();
    }

    public function test_admin_can_soft_delete_an_appointment(): void
    {
        $this->actingAsRole('Admin');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();
        $appointment = $this->appointment($patient, $doctor->id);

        $this->deleteJson("/api/v1/appointments/{$appointment->id}")->assertOk();

        $this->assertSoftDeleted('appointments', ['id' => $appointment->id]);
    }

    public function test_doctor_cannot_delete_an_appointment(): void
    {
        $this->actingAsRole('Doctor');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();
        $appointment = $this->appointment($patient, $doctor->id);

        $this->deleteJson("/api/v1/appointments/{$appointment->id}")->assertForbidden();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_soft_delete_an_invoice(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create();

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 50]],
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/invoices/{$invoiceId}")->assertOk();

        $this->assertSoftDeleted('invoices', ['id' => $invoiceId]);
    }
}
