<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionBillingRun;
use App\Models\AdmissionCharge;
use App\Models\AdmissionDeposit;
use App\Models\Bed;
use App\Models\BillableService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InpatientBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        Sanctum::actingAs($this->admin);
    }

    private function admission(): Admission
    {
        return Admission::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'status' => Admission::STATUS_ADMITTED,
        ]);
    }

    private function admissionWithBed(): Admission
    {
        $ward = Ward::factory()->create();
        $room = Room::factory()->create(['ward_id' => $ward->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);

        return Admission::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'ward_id' => $ward->id,
            'bed_id' => $bed->id,
            'status' => Admission::STATUS_ADMITTED,
            'admitted_at' => now()->subDays(2)->startOfDay(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function billableService(array $overrides = []): BillableService
    {
        return BillableService::create(array_merge([
            'code' => 'SVC-'.fake()->unique()->numerify('#####'),
            'name' => 'Nursing procedure',
            'category' => 'nursing',
            'unit' => 'service',
            'default_price' => 200,
            'is_active' => true,
            'price_source' => 'catalog',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function charge(Admission $admission, array $overrides = []): AdmissionCharge
    {
        return AdmissionCharge::create(array_merge([
            'admission_id' => $admission->id,
            'patient_id' => $admission->patient_id,
            'charge_date' => now()->toDateString(),
            'charge_time' => '10:00:00',
            'category' => AdmissionCharge::CATEGORY_SERVICE,
            'description' => 'Service charge',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'subtotal' => 100,
            'total' => 100,
            'status' => AdmissionCharge::STATUS_UNBILLED,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function createDailyInvoice(Admission $admission): Invoice
    {
        $this->charge($admission);

        $response = $this->postJson("/api/v1/admissions/{$admission->id}/billing/daily-invoice")
            ->assertCreated();

        return Invoice::findOrFail($response->json('data.invoice.id'));
    }

    public function test_client_supplied_price_is_ignored_for_catalog_services(): void
    {
        $admission = $this->admission();
        $service = $this->billableService(['default_price' => 200]);

        $response = $this->postJson("/api/v1/admissions/{$admission->id}/billing/charges", [
            'billable_service_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.unit_price', '200.00')
            ->assertJsonPath('data.subtotal', '400.00')
            ->assertJsonPath('data.total', '400.00');

        $this->assertDatabaseHas('admission_charges', [
            'id' => $response->json('data.id'),
            'billable_service_id' => $service->id,
            'unit_price' => 200,
            'total' => 400,
        ]);
    }

    public function test_inactive_catalog_service_cannot_be_charged(): void
    {
        $admission = $this->admission();
        $service = $this->billableService(['is_active' => false]);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/charges", [
            'billable_service_id' => $service->id,
            'quantity' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billable_service_id');

        $this->assertDatabaseCount('admission_charges', 0);
    }

    public function test_room_charges_are_generated_once_per_admission_day(): void
    {
        $admission = $this->admissionWithBed();

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/room-charges")
            ->assertOk()
            ->assertJsonPath('count', 3);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/room-charges")
            ->assertOk()
            ->assertJsonPath('count', 0);

        $roomCharges = AdmissionCharge::query()
            ->where('admission_id', $admission->id)
            ->where('category', AdmissionCharge::CATEGORY_ROOM)
            ->get();

        $this->assertCount(3, $roomCharges);
        $this->assertSame(4500.0, (float) $roomCharges->sum('total'));

        foreach ($roomCharges as $roomCharge) {
            $this->assertSame('room_daily', $roomCharge->source_type);
            $this->assertSame(AdmissionCharge::STATUS_UNBILLED, $roomCharge->status);
        }
    }

    public function test_daily_invoice_only_includes_unbilled_charges(): void
    {
        $admission = $this->admission();

        $unbilled = $this->charge($admission, ['description' => 'Unbilled service']);
        $voided = $this->charge($admission, [
            'description' => 'Voided service',
            'total' => 50,
            'subtotal' => 50,
            'unit_price' => 50,
            'status' => AdmissionCharge::STATUS_VOIDED,
        ]);

        $previousInvoice = Invoice::factory()->create([
            'patient_id' => $admission->patient_id,
            'admission_id' => $admission->id,
        ]);

        $invoiced = $this->charge($admission, [
            'description' => 'Already invoiced service',
            'total' => 25,
            'subtotal' => 25,
            'unit_price' => 25,
            'status' => AdmissionCharge::STATUS_INVOICED,
            'invoice_id' => $previousInvoice->id,
        ]);

        $response = $this->postJson("/api/v1/admissions/{$admission->id}/billing/daily-invoice")
            ->assertCreated()
            ->assertJsonPath('data.charges_invoiced', 1)
            ->assertJsonPath('data.invoice.total', '100.00');

        $this->assertCount(1, $response->json('data.invoice.items'));
        $this->assertSame('Unbilled service ('.now()->toDateString().')', $response->json('data.invoice.items.0.description'));

        $this->assertSame(AdmissionCharge::STATUS_INVOICED, $unbilled->fresh()->status);
        $this->assertSame(AdmissionCharge::STATUS_VOIDED, $voided->fresh()->status);
        $this->assertSame(AdmissionCharge::STATUS_INVOICED, $invoiced->fresh()->status);
        $this->assertSame($previousInvoice->id, $invoiced->fresh()->invoice_id);

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_daily_invoice_rolls_back_atomically_when_item_creation_fails(): void
    {
        $admission = $this->admission();
        $charge = $this->charge($admission);

        $event = 'eloquent.creating: '.InvoiceItem::class;

        Event::listen($event, function (): void {
            throw new \RuntimeException('Forced invoice item failure');
        });

        try {
            $this->postJson("/api/v1/admissions/{$admission->id}/billing/daily-invoice")
                ->assertStatus(500);
        } finally {
            Event::forget($event);
        }

        $this->assertSame(0, Invoice::where('admission_id', $admission->id)->count());
        $this->assertSame(0, InvoiceItem::count());
        $this->assertSame(AdmissionCharge::STATUS_UNBILLED, $charge->fresh()->status);
        $this->assertSame(
            0,
            AdmissionBillingRun::where('admission_id', $admission->id)
                ->where('status', AdmissionBillingRun::STATUS_COMPLETED)
                ->count()
        );
    }

    public function test_partial_and_multiple_payments_cannot_overpay_and_keep_balances_consistent(): void
    {
        $admission = $this->admission();
        $invoice = $this->createDailyInvoice($admission);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 101,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame('100.00', $invoice->fresh()->balance);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 40,
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('40.00', $invoice->amount_paid);
        $this->assertSame('60.00', $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 60,
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame('paid', $invoice->status);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(2, $invoice->payments()->count());
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_deposit_is_recorded_as_available_credit_in_the_ledger(): void
    {
        $admission = $this->admission();
        $this->createDailyInvoice($admission);

        $response = $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'amount' => 250,
            'method' => 'cash',
            'type' => 'deposit',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '250.00');

        $this->assertDatabaseHas('admission_deposits', [
            'id' => $response->json('data.admission_deposit_id'),
            'admission_id' => $admission->id,
            'amount' => 250,
            'balance' => 250,
            'status' => AdmissionDeposit::STATUS_AVAILABLE,
        ]);

        $ledger = $this->getJson("/api/v1/admissions/{$admission->id}/billing")
            ->assertOk()
            ->assertJsonPath('summary.total_invoiced', 100)
            ->assertJsonPath('summary.total_paid', 0)
            ->assertJsonPath('summary.available_deposits', 250)
            ->assertJsonPath('summary.outstanding_balance', 0);

        $this->assertCount(1, $ledger->json('deposits'));
    }

    public function test_discharge_reconciliation_does_not_double_bill_and_reports_the_right_balance(): void
    {
        $admission = $this->admissionWithBed();
        $this->charge($admission, ['description' => 'Discharge-day dressing']);

        $this->postJson("/api/v1/admissions/{$admission->id}/billing/payments", [
            'amount' => 1000,
            'type' => 'deposit',
        ])->assertCreated();

        $first = $this->postJson("/api/v1/admissions/{$admission->id}/billing/discharge-reconciliation", [
            'billing_date' => now()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.final_invoice.invoice_type', 'inpatient_discharge');

        $this->assertSame(4600.0, (float) $first->json('data.total_invoiced'));
        $this->assertSame(0.0, (float) $first->json('data.total_paid'));
        $this->assertSame(1000.0, (float) $first->json('data.available_deposits'));
        $this->assertSame(3600.0, (float) $first->json('data.outstanding_balance'));
        $this->assertCount(1, $first->json('data.invoices'));

        $second = $this->postJson("/api/v1/admissions/{$admission->id}/billing/discharge-reconciliation", [
            'billing_date' => now()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.final_invoice', null);

        $this->assertSame(4600.0, (float) $second->json('data.total_invoiced'));
        $this->assertSame(3600.0, (float) $second->json('data.outstanding_balance'));
        $this->assertCount(1, $second->json('data.invoices'));

        $invoices = Invoice::where('admission_id', $admission->id)->get();

        $this->assertCount(1, $invoices);
        $this->assertSame(4600.0, (float) $invoices->sum('total'));
        $this->assertSame(4600.0, (float) InvoiceItem::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->sum('amount'));

        $this->assertSame(4, AdmissionCharge::where('admission_id', $admission->id)->count());
        $this->assertSame(4, AdmissionCharge::where('admission_id', $admission->id)
            ->where('status', AdmissionCharge::STATUS_INVOICED)
            ->count());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function billingEndpointProvider(): array
    {
        return [
            'ledger' => ['get', '/api/v1/admissions/{admission}/billing', []],
            'generate room charges' => ['post', '/api/v1/admissions/{admission}/billing/room-charges', []],
            'add charge' => ['post', '/api/v1/admissions/{admission}/billing/charges', [
                'description' => 'Unauthorized charge',
                'quantity' => 1,
                'unit_price' => 10,
            ]],
            'daily invoice' => ['post', '/api/v1/admissions/{admission}/billing/daily-invoice', []],
            'payment' => ['post', '/api/v1/admissions/{admission}/billing/payments', ['amount' => 10]],
            'discharge reconciliation' => ['post', '/api/v1/admissions/{admission}/billing/discharge-reconciliation', []],
            'void charge' => ['post', '/api/v1/admission-charges/{charge}/void', ['reason' => 'Unauthorized void']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('billingEndpointProvider')]
    public function test_every_billing_endpoint_requires_authorization(string $method, string $path, array $payload): void
    {
        Sanctum::actingAs(User::factory()->create());

        $admission = $this->admission();
        $charge = $this->charge($admission);

        $path = str_replace(
            ['{admission}', '{charge}'],
            [$admission->id, $charge->id],
            $path,
        );

        $this->json($method, $path, $payload)->assertForbidden();

        $this->assertSame(AdmissionCharge::STATUS_UNBILLED, $charge->fresh()->status);
        $this->assertSame(0, Invoice::where('admission_id', $admission->id)->count());
        $this->assertSame(0, AdmissionDeposit::where('admission_id', $admission->id)->count());
    }
}
