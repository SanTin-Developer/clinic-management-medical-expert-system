<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Admission;
use App\Models\AdmissionBillingRun;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\SystemAlert;
use App\Models\TelegramGroupConfig;
use App\Models\User;
use App\Services\Alerts\SystemAlertGenerator;
use App\Services\Alerts\SystemAlertService;
use App\Services\Telegram\TelegramMessages;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SystemAlertTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function makeAlert(string $category, string $sourceKey): SystemAlert
    {
        return SystemAlert::create([
            'source_key' => $sourceKey,
            'category' => $category,
            'severity' => SystemAlert::SEVERITY_WARNING,
            'title' => 'Test '.$category.' alert',
            'message' => 'Test message',
            'module' => $category,
            'status' => SystemAlert::STATUS_ACTIVE,
        ]);
    }

    public function test_low_stock_creates_a_warning_alert(): void
    {
        $medicine = Medicine::factory()->create(['name' => 'Paracetamol', 'reorder_level' => 20]);

        InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 3,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        app(SystemAlertGenerator::class)->generate([SystemAlert::CATEGORY_INVENTORY]);

        $alerts = SystemAlert::query()
            ->where('category', SystemAlert::CATEGORY_INVENTORY)
            ->get();

        $this->assertCount(1, $alerts);
        $this->assertSame(SystemAlert::SEVERITY_WARNING, $alerts->first()->severity);
        $this->assertSame(SystemAlert::STATUS_ACTIVE, $alerts->first()->status);
    }

    public function test_out_of_stock_is_critical(): void
    {
        $medicine = Medicine::factory()->create(['name' => 'Amoxicillin', 'reorder_level' => 5]);

        InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 0,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        app(SystemAlertGenerator::class)->generate([SystemAlert::CATEGORY_INVENTORY]);

        $alert = SystemAlert::query()->where('category', SystemAlert::CATEGORY_INVENTORY)->first();

        $this->assertNotNull($alert);
        $this->assertSame(SystemAlert::SEVERITY_CRITICAL, $alert->severity);
    }

    public function test_expiring_stock_is_flagged(): void
    {
        $medicine = Medicine::factory()->create(['name' => 'Ibuprofen', 'reorder_level' => 100]);

        InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 100,
            'expiration_date' => now()->addDays(5)->toDateString(),
        ]);

        app(SystemAlertGenerator::class)->generate([SystemAlert::CATEGORY_INVENTORY]);

        $this->assertTrue(
            SystemAlert::query()
                ->where('category', SystemAlert::CATEGORY_INVENTORY)
                ->where('source_key', 'like', 'inventory.expiring.%')
                ->exists()
        );
    }

    public function test_repeated_generation_does_not_duplicate(): void
    {
        $medicine = Medicine::factory()->create(['reorder_level' => 20]);

        InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 1,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        $generator = app(SystemAlertGenerator::class);
        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);
        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);

        $this->assertSame(
            1,
            SystemAlert::query()->where('category', SystemAlert::CATEGORY_INVENTORY)->count()
        );
    }

    public function test_alert_is_resolved_when_condition_clears(): void
    {
        $medicine = Medicine::factory()->create(['reorder_level' => 20]);

        $item = InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 1,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        $generator = app(SystemAlertGenerator::class);
        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);

        $alert = SystemAlert::query()->where('category', SystemAlert::CATEGORY_INVENTORY)->first();
        $this->assertSame(SystemAlert::STATUS_ACTIVE, $alert->status);

        $item->update(['quantity' => 100]);
        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);

        $this->assertSame(SystemAlert::STATUS_RESOLVED, $alert->fresh()->status);
    }

    public function test_discharge_reconciliation_is_flagged_for_discharged_admission(): void
    {
        $patient = Patient::factory()->create();
        $admission = Admission::factory()->create([
            'patient_id' => $patient->id,
            'status' => Admission::STATUS_DISCHARGED,
            'discharged_at' => now(),
        ]);

        Invoice::factory()->create([
            'patient_id' => $patient->id,
            'admission_id' => $admission->id,
            'total' => 1200,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);

        app(SystemAlertGenerator::class)->generate([SystemAlert::CATEGORY_INPATIENT_BILLING]);

        $alert = SystemAlert::query()
            ->where('source_key', 'like', 'inpatient_billing.discharge_reconciliation.%')
            ->first();

        $this->assertNotNull($alert);
        $this->assertSame(SystemAlert::SEVERITY_WARNING, $alert->severity);
        $this->assertSame($admission->id, $alert->data['admission_id']);
    }

    public function test_failed_billing_run_is_critical(): void
    {
        $admission = Admission::factory()->create();

        AdmissionBillingRun::create([
            'admission_id' => $admission->id,
            'run_date' => now()->toDateString(),
            'generated_by' => null,
            'generated_at' => now(),
            'invoice_id' => null,
            'charges_count' => 0,
            'charges_total' => 0,
            'status' => AdmissionBillingRun::STATUS_FAILED,
            'error_message' => 'Gateway timeout',
            'ip_address' => null,
        ]);

        app(SystemAlertGenerator::class)->generate([SystemAlert::CATEGORY_INPATIENT_BILLING]);

        $alert = SystemAlert::query()
            ->where('source_key', 'like', 'inpatient_billing.billing_run_failed.%')
            ->first();

        $this->assertNotNull($alert);
        $this->assertSame(SystemAlert::SEVERITY_CRITICAL, $alert->severity);
    }

    public function test_manual_resolution_is_respected_while_condition_persists(): void
    {
        $medicine = Medicine::factory()->create(['reorder_level' => 20]);

        InventoryItem::factory()->create([
            'medicine_id' => $medicine->id,
            'quantity' => 1,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        $admin = $this->actingAsRole('Admin');
        $generator = app(SystemAlertGenerator::class);
        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);

        $alert = SystemAlert::query()->where('category', SystemAlert::CATEGORY_INVENTORY)->first();
        $this->postJson("/api/v1/system-alerts/{$alert->id}/resolve")->assertOk();

        $generator->generate([SystemAlert::CATEGORY_INVENTORY]);

        $this->assertSame(SystemAlert::STATUS_RESOLVED, $alert->fresh()->status);
    }

    public function test_lifecycle_read_acknowledge_then_resolve(): void
    {
        $this->actingAsRole('Admin');
        $alert = $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.lifecycle.test');

        $this->postJson("/api/v1/system-alerts/{$alert->id}/read")
            ->assertOk()
            ->assertJsonPath('data.status', SystemAlert::STATUS_ACTIVE)
            ->assertJsonPath('data.read_at', fn ($value) => ! is_null($value));

        // Reading an alert must not resolve the underlying condition.
        $this->assertSame(SystemAlert::STATUS_ACTIVE, $alert->fresh()->status);

        $this->postJson("/api/v1/system-alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', SystemAlert::STATUS_ACKNOWLEDGED);

        $this->postJson("/api/v1/system-alerts/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', SystemAlert::STATUS_RESOLVED);

        $this->assertSame($alert->id, $alert->fresh()->id);
    }

    public function test_mark_all_read_only_marks_visible_unread_alerts(): void
    {
        $this->actingAsRole('Admin');
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.bulk.1');
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.bulk.2');

        $this->getJson('/api/v1/system-alerts/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $this->postJson('/api/v1/system-alerts/read-all')
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->getJson('/api/v1/system-alerts/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        // Marking read never changes the alert lifecycle.
        $this->assertSame(
            2,
            SystemAlert::query()->where('status', SystemAlert::STATUS_ACTIVE)->count()
        );
    }

    public function test_summary_reports_counts_for_the_user(): void
    {
        $this->actingAsRole('Admin');
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.summary.1');

        $this->getJson('/api/v1/system-alerts/summary')
            ->assertOk()
            ->assertJsonPath('data.unread', 1)
            ->assertJsonPath('data.active', 1);
    }

    public function test_categories_are_scoped_by_permission(): void
    {
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.scope.1');
        $this->makeAlert(SystemAlert::CATEGORY_CLINICAL, 'clinical.scope.1');

        $pharmacist = $this->actingAsRole('Pharmacist');

        $response = $this->getJson('/api/v1/system-alerts')->assertOk();
        $categories = collect($response->json('data'))->pluck('category')->unique()->values();

        $this->assertContains(SystemAlert::CATEGORY_INVENTORY, $categories);
        $this->assertNotContains(SystemAlert::CATEGORY_CLINICAL, $categories);
        $this->assertNotContains(SystemAlert::CATEGORY_SYSTEM, $categories);
       }

    public function test_admin_sees_every_category(): void
    {
        $this->actingAsRole('Admin');
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.admin.1');
        $this->makeAlert(SystemAlert::CATEGORY_CLINICAL, 'clinical.admin.1');

        $this->getJson('/api/v1/system-alerts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_without_any_category_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/system-alerts')->assertForbidden();
        $this->getJson('/api/v1/system-alerts/unread-count')->assertForbidden();
    }

    public function test_state_changing_actions_are_refused_for_anonymous_users(): void
    {
        $alert = $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.guest.1');

        $this->postJson("/api/v1/system-alerts/{$alert->id}/read")->assertUnauthorized();
        $this->postJson("/api/v1/system-alerts/{$alert->id}/resolve")->assertUnauthorized();
        $this->postJson('/api/v1/system-alerts/read-all')->assertUnauthorized();
    }

    public function test_cannot_acknowledge_alert_from_another_category(): void
    {
        $alert = $this->makeAlert(SystemAlert::CATEGORY_CLINICAL, 'clinical.isolation.1');

        // A Pharmacist has inventory access but no clinical access.
        $this->actingAsRole('Pharmacist');

        $this->postJson("/api/v1/system-alerts/{$alert->id}/acknowledge")->assertForbidden();
        $this->postJson("/api/v1/system-alerts/{$alert->id}/resolve")->assertForbidden();
        $this->postJson("/api/v1/system-alerts/{$alert->id}/read")->assertForbidden();
    }

    public function test_search_and_filters_narrow_the_result_set(): void
    {
        $this->actingAsRole('Admin');
        $this->makeAlert(SystemAlert::CATEGORY_INVENTORY, 'inventory.filter.1');
        $this->makeAlert(SystemAlert::CATEGORY_CLINICAL, 'clinical.filter.1');

        $this->getJson('/api/v1/system-alerts?category=inventory')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', SystemAlert::CATEGORY_INVENTORY);

        $this->getJson('/api/v1/system-alerts?search=clinical')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', SystemAlert::CATEGORY_CLINICAL);
    }

    public function test_telegram_delivery_is_disabled_by_default(): void
    {
        Bus::fake();
        config()->set('alerts.telegram.enabled', false);

        app(SystemAlertService::class)->ensureAlert([
            'source_key' => 'system.telegram.disabled',
            'category' => SystemAlert::CATEGORY_SYSTEM,
            'severity' => SystemAlert::SEVERITY_CRITICAL,
            'title' => 'Critical system test',
            'module' => 'system',
        ]);

        Bus::assertNotDispatched(SendTelegramMessage::class);
    }

    public function test_telegram_delivery_requires_enabled_group_and_allowed_category(): void
    {
        Bus::fake();

        TelegramGroupConfig::query()->updateOrCreate([], [
            'chat_id' => 12345,
            'is_enabled' => true,
            'event_categories' => [TelegramGroupConfig::CATEGORY_SYSTEM_ALERTS],
        ]);

        config()->set('alerts.telegram.enabled', true);
        config()->set('alerts.telegram.categories', ['system']);
        config()->set('alerts.telegram.minimum_severity', 'critical');

        $service = app(SystemAlertService::class);

        // Below the configured minimum severity: nothing is queued.
        $service->ensureAlert([
            'source_key' => 'system.telegram.warning',
            'category' => SystemAlert::CATEGORY_SYSTEM,
            'severity' => SystemAlert::SEVERITY_WARNING,
            'title' => 'Warning system test',
            'module' => 'system',
        ]);
        Bus::assertNotDispatched(SendTelegramMessage::class);

        // Allowed category at or above the minimum severity: queued.
        $service->ensureAlert([
            'source_key' => 'system.telegram.critical',
            'category' => SystemAlert::CATEGORY_SYSTEM,
            'severity' => SystemAlert::SEVERITY_CRITICAL,
            'title' => 'Critical system test',
            'module' => 'system',
        ]);
        Bus::assertDispatched(SendTelegramMessage::class);
    }

    public function test_telegram_delivery_never_sends_patient_linked_alerts(): void
    {
        Bus::fake();

        TelegramGroupConfig::query()->updateOrCreate([], [
            'chat_id' => 12345,
            'is_enabled' => true,
            'event_categories' => [TelegramGroupConfig::CATEGORY_SYSTEM_ALERTS],
        ]);

        config()->set('alerts.telegram.enabled', true);
        config()->set('alerts.telegram.categories', [
            SystemAlert::CATEGORY_INVENTORY,
            SystemAlert::CATEGORY_SYSTEM,
            SystemAlert::CATEGORY_APPOINTMENTS,
            SystemAlert::CATEGORY_CLINICAL,
        ]);
        config()->set('alerts.telegram.minimum_severity', 'info');

        app(SystemAlertService::class)->ensureAlert([
            'source_key' => 'clinical.telegram.patient',
            'category' => SystemAlert::CATEGORY_CLINICAL,
            'patient_id' => Patient::factory()->create()->id,
            'severity' => SystemAlert::SEVERITY_CRITICAL,
            'title' => 'Nursing task overdue',
            'module' => 'nursing',
        ]);

        Bus::assertNotDispatched(SendTelegramMessage::class);
    }

    public function test_telegram_alert_copy_contains_no_urls_or_amounts(): void
    {
        $message = TelegramMessages::systemAlert(
            SystemAlert::SEVERITY_CRITICAL,
            'Inpatient billing run failed'
        );

        $this->assertStringContainsString('Inpatient billing run failed', $message);
        $this->assertStringNotContainsString('http', $message);
        $this->assertStringNotContainsString('USD', $message);
    }
}
