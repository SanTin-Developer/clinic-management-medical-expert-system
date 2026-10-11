<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingTest extends TestCase
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

    private function createInvoice(Patient $patient): int
    {
        $response = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id,
            'items' => [
                ['description' => 'Consultation', 'quantity' => 2, 'unit_price' => 50],
            ],
        ])->assertCreated();

        $response
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.total', '100.00')
            ->assertJsonPath('data.balance', '100.00')
            ->assertJsonPath('data.status', 'unpaid');

        return $response->json('data.id');
    }

    public function test_receptionist_can_create_invoice(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $this->createInvoice(Patient::factory()->create());
    }

    public function test_doctor_cannot_view_invoices(): void
    {
        Sanctum::actingAs($this->userWithRole('Doctor'));

        $this->getJson('/api/v1/invoices')->assertForbidden();
    }

    public function test_partial_payment_updates_status_and_balance(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $invoiceId = $this->createInvoice(Patient::factory()->create());

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 40,
            'method' => 'cash',
        ])->assertCreated()->assertJsonPath('data.amount', '40.00');

        $this->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.balance', '60.00');
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $invoiceId = $this->createInvoice(Patient::factory()->create());

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 100,
            'method' => 'card',
        ])->assertCreated();

        $this->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance', '0.00');
    }

    public function test_overpayment_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $invoiceId = $this->createInvoice(Patient::factory()->create());

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 150,
        ])->assertStatus(422);
    }

    public function test_invoice_with_payment_cannot_be_cancelled(): void
    {
        Sanctum::actingAs($this->userWithRole('Receptionist'));

        $invoiceId = $this->createInvoice(Patient::factory()->create());

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", ['amount' => 40])
            ->assertCreated();

        $this->postJson("/api/v1/invoices/{$invoiceId}/cancel")
            ->assertStatus(422);
    }
}
