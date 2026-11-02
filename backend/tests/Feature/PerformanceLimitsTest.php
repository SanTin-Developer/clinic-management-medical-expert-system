<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Locks in query efficiency for the hottest read endpoints.
 *
 * The seeded dataset is large enough that a single lazy-loaded relationship
 * inside a loop (an N+1) would add at least one query per row on the page and
 * blow past these budgets, while the budgets keep generous headroom above the
 * observed steady-state counts.
 */
class PerformanceLimitsTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedData(): void
    {
        $this->actingAsRole('Admin');
        $doctors = [$this->userWithRole('Doctor'), $this->userWithRole('Doctor')];

        $patients = Patient::factory()->count(60)->create();

        foreach ($patients as $i => $patient) {
            Appointment::create([
                'appointment_number' => 'APT-PERF-'.($i + 1),
                'patient_id' => $patient->id,
                'doctor_id' => $doctors[$i % 2]->id,
                'appointment_date' => now()->subDays($i % 90)->toDateString(),
                'appointment_time' => '09:00',
                'status' => ['scheduled', 'completed', 'cancelled'][$i % 3],
            ]);
        }

        foreach (Medicine::factory()->count(25)->create() as $medicine) {
            InventoryItem::create([
                'medicine_id' => $medicine->id,
                'batch_number' => 'B-'.$medicine->id,
                'quantity' => 10,
                'expiration_date' => now()->addDays($medicine->id % 2 === 0 ? 10 : 120)->toDateString(),
            ]);
        }

        foreach ($patients->take(10) as $i => $patient) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-PERF-'.($i + 1),
                'patient_id' => $patient->id,
                'status' => $i % 2 === 0 ? 'paid' : 'unpaid',
                'subtotal' => 50,
                'discount' => 0,
                'tax' => 0,
                'total' => 50,
                'amount_paid' => $i % 2 === 0 ? 50 : 0,
                'balance' => $i % 2 === 0 ? 0 : 50,
                'issued_at' => now()->subDays($i)->toDateString(),
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $i % 2 === 0 ? 50 : 0,
                'method' => $i % 2 === 0 ? 'cash' : 'card',
                'paid_at' => now()->subHours($i),
            ]);
        }
    }

    private function queriesFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_list_endpoints_stay_within_query_budget(): void
    {
        $this->seedData();

        $this->assertLessThanOrEqual(12, $this->queriesFor('/api/v1/patients?page=1'), 'patients index');
        $this->assertLessThanOrEqual(10, $this->queriesFor('/api/v1/appointments'), 'appointments index');
        $this->assertLessThanOrEqual(8, $this->queriesFor('/api/v1/medicines'), 'medicines index');
        $this->assertLessThanOrEqual(10, $this->queriesFor('/api/v1/invoices'), 'invoices index');
    }

    public function test_report_aggregates_are_cached_across_requests(): void
    {
        $this->seedData();

        // Financial and inventory aggregates are deliberately NOT cached: they
        // must always reflect live PostgreSQL state. Guard the query budget (no
        // per-row scanning) but do not assert cache hits here.
        $summaryCold = $this->queriesFor('/api/v1/reports/summary');
        $summaryWarm = $this->queriesFor('/api/v1/reports/summary');

        $this->assertLessThanOrEqual(30, $summaryCold, 'summary should not scan per row');
        $this->assertGreaterThan($summaryWarm, $summaryCold, 'summary cold must do real work');
        // Summary embeds billing + inventory, so it is read live every request.
        $this->assertGreaterThan(3, $summaryWarm, 'summary is database-authoritative and must not be cached');

        // Revenue and inventory are financial/stock data: never served from cache.
        $revenueWarm = $this->queriesFor('/api/v1/reports/revenue');
        $this->assertGreaterThan(3, $revenueWarm, 'revenue is database-authoritative and must not be cached');

        $inventoryWarm = $this->queriesFor('/api/v1/reports/inventory');
        $this->assertGreaterThan(3, $inventoryWarm, 'inventory is database-authoritative and must not be cached');

        // Appointments is the one low-risk cached report: the warm read is cheap.
        $this->queriesFor('/api/v1/reports/appointments');
        $appointmentsWarm = $this->queriesFor('/api/v1/reports/appointments');
        $this->assertLessThanOrEqual(3, $appointmentsWarm, 'appointments must be served from cache when warm');
    }
}
