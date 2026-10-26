<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Patient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_export_appointments_csv(): void
    {
        $this->actingAsRole('Admin');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        Appointment::create([
            'appointment_number' => 'APT-EXP-1',
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => '2030-01-01',
            'appointment_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $response = $this->get('/api/v1/reports/appointments/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('scheduled,1', $response->streamedContent());
    }

    public function test_admin_can_export_revenue_csv(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['price' => 80]);

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 80,
            'method' => 'cash',
        ])->assertCreated();

        $response = $this->get('/api/v1/reports/revenue/export');

        $response->assertOk();
        $this->assertStringContainsString('cash,80', $response->streamedContent());
    }

    public function test_admin_can_export_inventory_csv(): void
    {
        $this->actingAsRole('Admin');
        $medicine = Medicine::factory()->create(['reorder_level' => 10]);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $response = $this->get('/api/v1/reports/inventory/export');

        $response->assertOk();
        $this->assertStringContainsString('Low stock', $response->streamedContent());
    }

    public function test_unknown_report_export_returns_not_found(): void
    {
        $this->actingAsRole('Admin');

        $this->get('/api/v1/reports/nonsense/export')->assertNotFound();
    }

    public function test_admin_can_export_appointments_pdf(): void
    {
        $this->actingAsRole('Admin');
        $doctor = $this->userWithRole('Doctor');
        $patient = Patient::factory()->create();

        Appointment::create([
            'appointment_number' => 'APT-PDF-1',
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => '2030-01-01',
            'appointment_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $response = $this->get('/api/v1/reports/appointments/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('appointments-report.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_revenue_pdf_uses_supplied_date_range(): void
    {
        $this->actingAsRole('Admin');
        $patient = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['price' => 80]);

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 80,
            'method' => 'cash',
        ])->assertCreated();

        $this->get('/api/v1/reports/revenue/pdf?from=2000-01-01&to=2100-01-01')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_rejects_invalid_date_range(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/reports/revenue/pdf?from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_unknown_report_pdf_returns_not_found(): void
    {
        $this->actingAsRole('Admin');

        $this->get('/api/v1/reports/nonsense/pdf')->assertNotFound();
    }

    public function test_receptionist_cannot_export_reports(): void
    {
        $this->actingAsRole('Receptionist');

        $this->get('/api/v1/reports/appointments/export')->assertForbidden();
        $this->get('/api/v1/reports/appointments/pdf')->assertForbidden();
    }
}
