<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_view_dashboard_analytics(): void
    {
        $admin = $this->userWithRole('Admin');
        Sanctum::actingAs($admin);

        $patient = Patient::factory()->create();

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        Appointment::create([
            'appointment_number' => 'APT-000001',
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'status' => 'scheduled',
        ]);

        Appointment::create([
            'appointment_number' => 'APT-000002',
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'appointment_date' => $today,
            'appointment_time' => '10:00',
            'status' => 'completed',
        ]);

        Appointment::create([
            'appointment_number' => 'APT-000003',
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'appointment_date' => $yesterday,
            'appointment_time' => '11:00',
            'status' => 'scheduled',
        ]);

        $medicine = Medicine::factory()->create(['reorder_level' => 10]);
        InventoryItem::create(['medicine_id' => $medicine->id, 'quantity' => 3]);

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 80]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 80,
            'method' => 'cash',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/dashboard/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'range' => ['days', 'months'],
                    'appointments' => ['today', 'this_month', 'total', 'trend', 'by_status'],
                    'patients' => ['total', 'new_this_month', 'trend'],
                    'revenue' => ['total', 'this_month', 'trend'],
                    'inventory' => ['active_medicines', 'low_stock', 'expiring_within_30_days'],
                ],
            ]);

        $data = $response->json('data');

        $this->assertCount(14, $data['appointments']['trend']);
        $this->assertCount(6, $data['revenue']['trend']);
        $this->assertSame(2, $data['appointments']['today']);
        $this->assertSame(3, $data['appointments']['total']);
        $this->assertSame(80.0, (float) $data['revenue']['total']);
        $this->assertSame(1, $data['inventory']['low_stock']);
        $this->assertGreaterThanOrEqual(1, $data['patients']['total']);

        $scheduled = collect($data['appointments']['by_status'])->firstWhere('status', 'scheduled');
        $this->assertSame(2, $scheduled['total']);
    }

    public function test_dashboard_analytics_requires_reports_permission(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $this->getJson('/api/v1/dashboard/analytics')->assertForbidden();
    }
}
