<?php

namespace App\Services\Alerts;

use App\Models\Admission;
use App\Models\AdmissionBillingRun;
use App\Models\AdmissionCharge;
use App\Models\AdmissionDeposit;
use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\InventoryItem;
use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\SystemAlert;
use App\Models\TelegramDeliveryLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runs the deterministic alert detectors.
 *
 * Every detector returns the exact set of source keys that are currently true.
 * The service then resolves any previously active alert for that detector that
 * was not seen. Detectors never mutate stock, invoices, appointments, or
 * clinical records; they only read.
 */
class SystemAlertGenerator
{
    public function __construct(private readonly SystemAlertService $alerts)
    {
    }

    /**
     * @param  array<int, string>|null  $only
     * @return array<string, array{seen: int, resolved: int, error?: string}>
     */
    public function generate(?array $only = null): array
    {
        $detectors = [
            SystemAlert::CATEGORY_INVENTORY => [
                'prefix' => 'inventory.',
                'run' => fn () => $this->detectInventory(),
            ],
            SystemAlert::CATEGORY_INPATIENT_BILLING => [
                'prefix' => 'inpatient_billing.',
                'run' => fn () => $this->detectInpatientBilling(),
            ],
            SystemAlert::CATEGORY_APPOINTMENTS => [
                'prefix' => 'appointments.',
                'run' => fn () => $this->detectAppointments(),
            ],
            SystemAlert::CATEGORY_STAFF => [
                'prefix' => 'staff.',
                'run' => fn () => $this->detectStaffLicenses(),
            ],
            SystemAlert::CATEGORY_ATTENDANCE => [
                'prefix' => 'attendance.',
                'run' => fn () => $this->detectAttendance(),
            ],
            SystemAlert::CATEGORY_CLINICAL => [
                'prefix' => 'clinical.',
                'run' => fn () => $this->detectClinical(),
            ],
            SystemAlert::CATEGORY_SYSTEM => [
                'prefix' => 'system.',
                'run' => fn () => $this->detectSystemHealth(),
            ],
        ];

        $selected = $only === null
            ? array_keys($detectors)
            : array_values(array_intersect(array_keys($detectors), $only));

        $results = [];

        foreach ($selected as $category) {
            $detector = $detectors[$category];

            try {
                /** @var array<int, string> $seen */
                $seen = $detector['run']();
                $resolved = $this->alerts->resolveMissing($detector['prefix'], $seen);

                $results[$category] = [
                    'seen' => count($seen),
                    'resolved' => $resolved,
                ];
            } catch (Throwable $exception) {
                Log::error('System alert detector failed.', [
                    'category' => $category,
                    'error' => $exception->getMessage(),
                ]);

                // Do not resolve anything when a scan fails: a transient error
                // must never silently clear real operational alerts.
                $results[$category] = [
                    'seen' => 0,
                    'resolved' => 0,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    private function detectInventory(): array
    {
        $seen = [];
        $defaultThreshold = (int) config('alerts.inventory.default_reorder_level', 20);
        $expiryDays = max(0, (int) config('alerts.inventory.expiry_warning_days', 30));
        $expiryCutoff = Carbon::now()->addDays($expiryDays)->endOfDay();

        InventoryItem::query()
            ->with('medicine')
            ->lazyById(200)
            ->each(function (InventoryItem $item) use (&$seen, $defaultThreshold, $expiryCutoff) {
                $medicineName = $item->medicine?->name ?? $item->batch_number ?? 'Inventory item';
                $quantity = (int) $item->quantity;
                $reorderLevel = $item->medicine?->reorder_level;

                $threshold = $reorderLevel === null || $reorderLevel === ''
                    ? $defaultThreshold
                    : (int) $reorderLevel;

                if ($quantity <= $threshold) {
                    $key = "inventory.low_stock.{$item->id}";
                    $seen[] = $key;

                    $this->alerts->ensureAlert([
                        'source_key' => $key,
                        'category' => SystemAlert::CATEGORY_INVENTORY,
                        'severity' => $quantity <= 0
                            ? SystemAlert::SEVERITY_CRITICAL
                            : SystemAlert::SEVERITY_WARNING,
                        'title' => 'Low stock: '.$medicineName,
                        'message' => "Only {$quantity} left in stock (reorder level: {$threshold}). Batch: ".($item->batch_number ?? 'N/A').'.',
                        'module' => 'inventory',
                        'url' => '/inventory',
                        'data' => [
                            'inventory_item_id' => $item->id,
                            'medicine_id' => $item->medicine_id,
                            'batch_number' => $item->batch_number,
                            'quantity' => $quantity,
                            'threshold' => $threshold,
                            'location' => $item->location,
                        ],
                    ]);
                }

                if ($item->expiration_date !== null && $item->expiration_date->lte($expiryCutoff)) {
                    $key = "inventory.expiring.{$item->id}";
                    $seen[] = $key;

                    $expired = $item->expiration_date->isPast();

                    $this->alerts->ensureAlert([
                        'source_key' => $key,
                        'category' => SystemAlert::CATEGORY_INVENTORY,
                        'severity' => $expired
                            ? SystemAlert::SEVERITY_CRITICAL
                            : SystemAlert::SEVERITY_WARNING,
                        'title' => ($expired ? 'Expired stock: ' : 'Expiring stock: ').$medicineName,
                        'message' => 'Batch '.($item->batch_number ?? 'N/A').' expires on '.$item->expiration_date->format('Y-m-d').'.',
                        'module' => 'inventory',
                        'url' => '/inventory',
                        'data' => [
                            'inventory_item_id' => $item->id,
                            'medicine_id' => $item->medicine_id,
                            'batch_number' => $item->batch_number,
                            'quantity' => $quantity,
                            'expiration_date' => $item->expiration_date->format('Y-m-d'),
                            'expired' => $expired,
                        ],
                    ]);
                }
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectInpatientBilling(): array
    {
        $seen = [];
        $warning = (float) config('alerts.inpatient_billing.outstanding_warning', 500);
        $critical = (float) config('alerts.inpatient_billing.outstanding_critical', 2000);
        $unbilledWarning = (float) config('alerts.inpatient_billing.unbilled_warning', 100);
        $unbilledAgeDays = max(1, (int) config('alerts.inpatient_billing.unbilled_age_days', 3));

        $admissions = Admission::query()
            ->where('status', Admission::STATUS_ADMITTED)
            ->with('patient:id,patient_number,first_name,last_name')
            ->withSum(['invoices as total_invoiced' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->withSum(['invoices as total_paid' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'amount_paid')
            ->withSum(['deposits as available_deposits' => fn ($query) => $query
                ->where('status', AdmissionDeposit::STATUS_AVAILABLE)
                ->where('balance', '>', 0)], 'balance')
            ->withSum(['charges as unbilled_total' => fn ($query) => $query
                ->where('status', AdmissionCharge::STATUS_UNBILLED)], 'total')
            ->withMin(['charges as oldest_unbilled_date' => fn ($query) => $query
                ->where('status', AdmissionCharge::STATUS_UNBILLED)], 'charge_date')
            ->get();

        foreach ($admissions as $admission) {
            $patientName = $this->patientName($admission->patient);
            $totalInvoiced = (float) ($admission->total_invoiced ?? 0);
            $totalPaid = (float) ($admission->total_paid ?? 0);
            $deposits = (float) ($admission->available_deposits ?? 0);
            $outstanding = round(max(0, $totalInvoiced - $totalPaid - $deposits), 2);

            if ($outstanding > 0 && $outstanding >= $warning) {
                $key = "inpatient_billing.outstanding.{$admission->id}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_INPATIENT_BILLING,
                    'patient_id' => $admission->patient_id,
                    'severity' => $outstanding >= $critical
                        ? SystemAlert::SEVERITY_CRITICAL
                        : SystemAlert::SEVERITY_WARNING,
                    'title' => 'Outstanding inpatient balance: '.$patientName,
                    'message' => 'Admission '.$admission->admission_number.' has an outstanding balance of USD '.number_format($outstanding, 2).'.',
                    'module' => 'inpatient_billing',
                    'url' => '/admissions/'.$admission->id.'/billing',
                    'data' => [
                        'admission_id' => $admission->id,
                        'admission_number' => $admission->admission_number,
                        'outstanding_balance' => $outstanding,
                        'total_invoiced' => round($totalInvoiced, 2),
                        'total_paid' => round($totalPaid, 2),
                        'available_deposits' => round($deposits, 2),
                    ],
                ]);
            }

            $unbilled = (float) ($admission->unbilled_total ?? 0);
            $oldest = $admission->oldest_unbilled_date
                ? Carbon::parse($admission->oldest_unbilled_date)->startOfDay()
                : null;

            if ($unbilled > 0 && $unbilled >= $unbilledWarning && $oldest !== null
                && $oldest->lte(Carbon::now()->subDays($unbilledAgeDays)->startOfDay())) {
                $key = "inpatient_billing.unbilled.{$admission->id}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_INPATIENT_BILLING,
                    'patient_id' => $admission->patient_id,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Unbilled inpatient charges: '.$patientName,
                    'message' => 'Admission '.$admission->admission_number.' has USD '.number_format($unbilled, 2).' unbilled since '.$oldest->format('Y-m-d').'.',
                    'module' => 'inpatient_billing',
                    'url' => '/admissions/'.$admission->id.'/billing',
                    'data' => [
                        'admission_id' => $admission->id,
                        'admission_number' => $admission->admission_number,
                        'unbilled_total' => round($unbilled, 2),
                        'oldest_charge_date' => $oldest->format('Y-m-d'),
                    ],
                ]);
            }
        }

        // A clinically discharged admission that still carries an outstanding
        // balance has not completed financial reconciliation. This is a
        // read-only signal: it never marks the admission reconciled or alters
        // any financial record.
        $discharged = Admission::query()
            ->where('status', Admission::STATUS_DISCHARGED)
            ->with('patient:id,patient_number,first_name,last_name')
            ->withSum(['invoices as total_invoiced' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->withSum(['invoices as total_paid' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'amount_paid')
            ->withSum(['deposits as available_deposits' => fn ($query) => $query
                ->where('status', AdmissionDeposit::STATUS_AVAILABLE)
                ->where('balance', '>', 0)], 'balance')
            ->get();

        foreach ($discharged as $admission) {
            $totalInvoiced = (float) ($admission->total_invoiced ?? 0);
            $totalPaid = (float) ($admission->total_paid ?? 0);
            $deposits = (float) ($admission->available_deposits ?? 0);
            $outstanding = round(max(0, $totalInvoiced - $totalPaid - $deposits), 2);

            if ($outstanding <= 0) {
                continue;
            }

            $key = "inpatient_billing.discharge_reconciliation.{$admission->id}";
            $seen[] = $key;

            $this->alerts->ensureAlert([
                'source_key' => $key,
                'category' => SystemAlert::CATEGORY_INPATIENT_BILLING,
                'patient_id' => $admission->patient_id,
                'severity' => $outstanding >= $critical
                    ? SystemAlert::SEVERITY_CRITICAL
                    : SystemAlert::SEVERITY_WARNING,
                'title' => 'Discharge reconciliation pending: '.$this->patientName($admission->patient),
                'message' => 'Admission '.$admission->admission_number.' was discharged with an outstanding balance of USD '.number_format($outstanding, 2).'.',
                'module' => 'inpatient_billing',
                'url' => '/admissions/'.$admission->id.'/billing',
                'data' => [
                    'admission_id' => $admission->id,
                    'admission_number' => $admission->admission_number,
                    'outstanding_balance' => $outstanding,
                    'total_invoiced' => round($totalInvoiced, 2),
                    'total_paid' => round($totalPaid, 2),
                    'available_deposits' => round($deposits, 2),
                ],
            ]);
        }

        // Inpatient billing runs that failed and have not been re-run since.
        $runLookback = Carbon::now()
            ->subDays(max(1, (int) config('alerts.inpatient_billing.billing_run_lookback_days', 7)));

        AdmissionBillingRun::query()
            ->where('status', AdmissionBillingRun::STATUS_FAILED)
            ->whereDate('run_date', '>=', $runLookback->toDateString())
            ->lazyById(200)
            ->each(function (AdmissionBillingRun $run) use (&$seen) {
                $key = "inpatient_billing.billing_run_failed.{$run->id}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_INPATIENT_BILLING,
                    'severity' => SystemAlert::SEVERITY_CRITICAL,
                    'title' => 'Inpatient billing run failed',
                    'message' => 'The billing run for admission '.$run->admission_id.' on '.$run->run_date->format('Y-m-d').' failed.'.($run->error_message ? ' Reason: '.$run->error_message : ''),
                    'module' => 'inpatient_billing',
                    'url' => '/admissions/'.$run->admission_id.'/billing',
                    'data' => [
                        'admission_id' => $run->admission_id,
                        'run_date' => $run->run_date->format('Y-m-d'),
                    ],
                ]);
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectAppointments(): array
    {
        $seen = [];
        $since = Carbon::now()->subDays(max(1, (int) config('alerts.appointments.lookback_days', 14)))->toDateString();
        $today = Carbon::now()->toDateString();

        Appointment::query()
            ->with('patient:id,patient_number,first_name,last_name')
            ->whereDate('appointment_date', '>=', $since)
            ->whereDate('appointment_date', '<', $today)
            ->whereIn('status', ['no_show', 'scheduled', 'confirmed', 'in_progress'])
            ->lazyById(200)
            ->each(function (Appointment $appointment) use (&$seen) {
                $isNoShow = $appointment->status === 'no_show';
                $key = $isNoShow
                    ? "appointments.no_show.{$appointment->id}"
                    : "appointments.missed.{$appointment->id}";
                $seen[] = $key;

                $patientName = $this->patientName($appointment->patient);
                $date = $appointment->appointment_date?->format('Y-m-d') ?? 'unknown date';

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_APPOINTMENTS,
                    'patient_id' => $appointment->patient_id,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => ($isNoShow ? 'No-show appointment: ' : 'Unresolved past appointment: ').$patientName,
                    'message' => 'Appointment '.$appointment->appointment_number.' on '.$date.' at '.$appointment->appointment_time.' is marked '.$appointment->status.'.',
                    'module' => 'appointments',
                    'url' => '/appointments/'.$appointment->id,
                    'data' => [
                        'appointment_id' => $appointment->id,
                        'appointment_number' => $appointment->appointment_number,
                        'appointment_date' => $date,
                        'appointment_time' => $appointment->appointment_time,
                        'status' => $appointment->status,
                    ],
                ]);
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectStaffLicenses(): array
    {
        $seen = [];
        $days = max(0, (int) config('alerts.staff.license_expiry_days', 30));
        $cutoff = Carbon::today()->addDays($days);
        $today = Carbon::today();

        Staff::query()
            ->whereNotNull('license_expiry')
            ->whereNotIn('employment_status', [Staff::STATUS_RESIGNED, Staff::STATUS_TERMINATED])
            ->whereDate('license_expiry', '<=', $cutoff->toDateString())
            ->lazyById(200)
            ->each(function (Staff $staff) use (&$seen, $today) {
                $key = "staff.license_expiry.{$staff->id}";
                $seen[] = $key;

                $expired = $staff->license_expiry->lt($today);

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_STAFF,
                    'severity' => $expired
                        ? SystemAlert::SEVERITY_CRITICAL
                        : SystemAlert::SEVERITY_WARNING,
                    'title' => ($expired ? 'Expired license: ' : 'License expiring: ').$staff->full_name,
                    'message' => 'License '.($staff->license_number ?? '(not recorded)').' '.($expired ? 'expired on ' : 'expires on ').$staff->license_expiry->format('Y-m-d').'.',
                    'module' => 'staff',
                    'url' => '/staff/'.$staff->id,
                    'data' => [
                        'staff_id' => $staff->id,
                        'staff_number' => $staff->staff_number,
                        'license_number' => $staff->license_number,
                        'license_expiry' => $staff->license_expiry->format('Y-m-d'),
                        'expired' => $expired,
                    ],
                ]);
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectAttendance(): array
    {
        $seen = [];
        $lookbackDays = max(1, (int) config('alerts.attendance.lookback_days', 14));
        $absenceThreshold = max(1, (int) config('alerts.attendance.absence_threshold', 3));
        $lateThreshold = max(1, (int) config('alerts.attendance.late_threshold', 3));
        $lateMinutes = max(1, (int) config('alerts.attendance.late_minutes', 30));
        $flagMissingClockOut = (bool) config('alerts.attendance.flag_missing_clock_out', true);
        $since = Carbon::now()->subDays($lookbackDays)->toDateString();

        /** @var Collection<int, AttendanceRecord> $records */
        $records = AttendanceRecord::query()
            ->whereDate('work_date', '>=', $since)
            ->get(['user_id', 'work_date', 'status', 'late_minutes', 'missing_clock_out']);

        $names = User::query()
            ->whereIn('id', $records->pluck('user_id')->unique()->filter()->values())
            ->pluck('name', 'id');

        foreach ($records->groupBy('user_id') as $userId => $userRecords) {
            $name = $names[$userId] ?? 'Team member #'.$userId;

            $absences = $userRecords->where('status', 'absent')->count();
            if ($absences >= $absenceThreshold) {
                $key = "attendance.absences.{$userId}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_ATTENDANCE,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Repeated absences: '.$name,
                    'message' => "{$absences} absences recorded in the last {$lookbackDays} days.",
                    'module' => 'attendance',
                    'url' => '/attendance',
                    'data' => [
                        'user_id' => (int) $userId,
                        'absences' => $absences,
                        'lookback_days' => $lookbackDays,
                    ],
                ]);
            }

            $lateCount = $userRecords
                ->filter(fn (AttendanceRecord $record) => (int) $record->late_minutes >= $lateMinutes)
                ->count();

            if ($lateCount >= $lateThreshold) {
                $key = "attendance.late.{$userId}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_ATTENDANCE,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Repeated lateness: '.$name,
                    'message' => "{$lateCount} late arrivals of {$lateMinutes}+ minutes in the last {$lookbackDays} days.",
                    'module' => 'attendance',
                    'url' => '/attendance',
                    'data' => [
                        'user_id' => (int) $userId,
                        'late_count' => $lateCount,
                        'late_minutes_threshold' => $lateMinutes,
                        'lookback_days' => $lookbackDays,
                    ],
                ]);
            }

            $missingClockOuts = $userRecords->where('missing_clock_out', true)->count();
            if ($flagMissingClockOut && $missingClockOuts > 0) {
                $key = "attendance.missing_clock_out.{$userId}";
                $seen[] = $key;

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_ATTENDANCE,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Missing clock-out: '.$name,
                    'message' => "{$missingClockOuts} attendance record(s) in the last {$lookbackDays} days have no clock-out.",
                    'module' => 'attendance',
                    'url' => '/attendance',
                    'data' => [
                        'user_id' => (int) $userId,
                        'missing_clock_outs' => $missingClockOuts,
                        'lookback_days' => $lookbackDays,
                    ],
                ]);
            }
        }

        // Published shifts for today whose start time has passed by the grace
        // period, where the scheduled user has no attendance record at all.
        $graceMinutes = max(1, (int) config('alerts.attendance.no_checkin_grace_minutes', 60));
        $clinicTz = (string) config('attendance.timezone', 'Asia/Phnom_Penh');
        $now = Carbon::now();
        $flagBefore = $now->copy()->subMinutes($graceMinutes);
        // Shift dates and times are authored in the clinic timezone, not UTC.
        $today = $now->copy()->setTimezone($clinicTz)->toDateString();

        $clockedToday = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->pluck('user_id')
            ->flip();

        $shiftNames = User::query()
            ->whereIn('id', Shift::query()
                ->whereNotNull('published_at')
                ->where('status', 'scheduled')
                ->whereDate('shift_date', $today)
                ->pluck('user_id'))
            ->pluck('name', 'id');

        Shift::query()
            ->whereNotNull('published_at')
            ->where('status', 'scheduled')
            ->whereDate('shift_date', $today)
            ->lazyById(200)
            ->each(function (Shift $shift) use (&$seen, $clockedToday, $shiftNames, $flagBefore, $graceMinutes, $clinicTz) {
                if ($shift->user_id === null || $clockedToday->has($shift->user_id)) {
                    return;
                }

                if (empty($shift->start_time)) {
                    return;
                }

                try {
                    $start = Carbon::parse(
                        $shift->shift_date->toDateString().' '.substr((string) $shift->start_time, 0, 5),
                        $clinicTz,
                    );
                } catch (Throwable) {
                    return;
                }

                if ($start->gt($flagBefore)) {
                    return;
                }

                $key = "attendance.no_checkin.{$shift->id}";
                $seen[] = $key;

                $name = $shiftNames[$shift->user_id] ?? ('Team member #'.$shift->user_id);

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_ATTENDANCE,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Expected staff not checked in: '.$name,
                    'message' => 'A published '.($shift->type ?? 'scheduled').' shift starting '.$start->format('H:i').' today has no clock-in record.',
                    'module' => 'attendance',
                    'url' => '/attendance',
                    'data' => [
                        'user_id' => (int) $shift->user_id,
                        'shift_id' => $shift->id,
                        'shift_type' => $shift->type,
                        'grace_minutes' => $graceMinutes,
                    ],
                ]);
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectClinical(): array
    {
        $seen = [];
        $taskGrace = max(0, (int) config('alerts.clinical.task_grace_minutes', 30));
        $taskCriticalHours = max(1, (int) config('alerts.clinical.task_critical_hours', 24));
        $medicationGrace = max(0, (int) config('alerts.clinical.medication_grace_minutes', 60));

        NursingTask::query()
            ->with('patient:id,patient_number,first_name,last_name')
            ->whereIn('status', [NursingTask::STATUS_PENDING, NursingTask::STATUS_IN_PROGRESS])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', Carbon::now()->subMinutes($taskGrace))
            ->lazyById(200)
            ->each(function (NursingTask $task) use (&$seen, $taskCriticalHours) {
                $key = "clinical.nursing_task_overdue.{$task->id}";
                $seen[] = $key;

                $critical = $task->due_at->lte(Carbon::now()->subHours($taskCriticalHours));

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_CLINICAL,
                    'patient_id' => $task->patient_id,
                    'severity' => $critical ? SystemAlert::SEVERITY_CRITICAL : SystemAlert::SEVERITY_WARNING,
                    'title' => 'Nursing task overdue: '.$this->patientName($task->patient),
                    'message' => $task->title.' was due at '.$task->due_at->format('Y-m-d H:i').'.',
                    'module' => 'nursing',
                    'url' => '/nursing-tasks',
                    'data' => [
                        'nursing_task_id' => $task->id,
                        'task_type' => $task->task_type,
                        'priority' => $task->priority,
                        'due_at' => $task->due_at->toIso8601String(),
                        'status' => $task->status,
                    ],
                ]);
            });

        MedicationAdministration::query()
            ->with(['patient:id,patient_number,first_name,last_name', 'medicine:id,name'])
            ->where('status', MedicationAdministration::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now()->subMinutes($medicationGrace))
            ->lazyById(200)
            ->each(function (MedicationAdministration $administration) use (&$seen) {
                $key = "clinical.medication_overdue.{$administration->id}";
                $seen[] = $key;

                $medicine = $administration->medicine?->name ?? 'Medication';

                $this->alerts->ensureAlert([
                    'source_key' => $key,
                    'category' => SystemAlert::CATEGORY_CLINICAL,
                    'patient_id' => $administration->patient_id,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Medication overdue: '.$this->patientName($administration->patient),
                    'message' => $medicine.' was scheduled at '.$administration->scheduled_at->format('Y-m-d H:i').' and has not been recorded.',
                    'module' => 'mar',
                    'url' => '/mar',
                    'data' => [
                        'medication_administration_id' => $administration->id,
                        'medicine_id' => $administration->medicine_id,
                        'dose' => $administration->dose,
                        'scheduled_at' => $administration->scheduled_at->toIso8601String(),
                    ],
                ]);
            });

        return $seen;
    }

    /**
     * @return array<int, string>
     */
    private function detectSystemHealth(): array
    {
        $seen = [];

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $threshold = max(1, (int) config('alerts.system.failed_jobs_threshold', 1));

            if ($failed >= $threshold) {
                $seen[] = 'system.failed_jobs';

                $this->alerts->ensureAlert([
                    'source_key' => 'system.failed_jobs',
                    'category' => SystemAlert::CATEGORY_SYSTEM,
                    'severity' => SystemAlert::SEVERITY_CRITICAL,
                    'title' => 'Failed background jobs need attention',
                    'message' => "{$failed} failed job(s) are waiting in the queue.",
                    'module' => 'system',
                    'url' => '/settings',
                    'data' => ['failed_jobs' => $failed],
                ]);
            }
        }

        $queueTable = (string) config('queue.connections.database.table', 'jobs');

        if (Schema::hasTable($queueTable)) {
            $backlog = DB::table($queueTable)->count();
            $threshold = max(1, (int) config('alerts.system.queue_backlog_threshold', 500));

            if ($backlog >= $threshold) {
                $seen[] = 'system.queue_backlog';

                $this->alerts->ensureAlert([
                    'source_key' => 'system.queue_backlog',
                    'category' => SystemAlert::CATEGORY_SYSTEM,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Background queue backlog is growing',
                    'message' => "{$backlog} queued job(s) are waiting to be processed.",
                    'module' => 'system',
                    'url' => '/settings',
                    'data' => ['queue_backlog' => $backlog],
                ]);
            }
        }

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free !== false && $total !== false && $total > 0) {
            $freePercent = round(($free / $total) * 100, 1);
            $threshold = max(1, (int) config('alerts.system.disk_free_percent_threshold', 10));

            if ($freePercent <= $threshold) {
                $seen[] = 'system.disk_space';

                $this->alerts->ensureAlert([
                    'source_key' => 'system.disk_space',
                    'category' => SystemAlert::CATEGORY_SYSTEM,
                    'severity' => SystemAlert::SEVERITY_CRITICAL,
                    'title' => 'Server storage is running low',
                    'message' => "Only {$freePercent}% of storage is free.",
                    'module' => 'system',
                    'url' => '/settings',
                    'data' => ['free_percent' => $freePercent],
                ]);
            }
        }

        // Telegram notification delivery failures. The delivery log is the
        // authoritative record; the alert never contains message content or
        // patient data.
        if (Schema::hasTable('telegram_delivery_logs')) {
            $lookbackHours = max(1, (int) config('alerts.system.telegram_failure_lookback_hours', 24));
            $since = Carbon::now()->subHours($lookbackHours);

            $failed = DB::table('telegram_delivery_logs')
                ->where('status', TelegramDeliveryLog::STATUS_FAILED)
                ->where('created_at', '>=', $since)
                ->count();

            if ($failed >= 1) {
                $seen[] = 'system.telegram_delivery_failures';

                $this->alerts->ensureAlert([
                    'source_key' => 'system.telegram_delivery_failures',
                    'category' => SystemAlert::CATEGORY_SYSTEM,
                    'severity' => SystemAlert::SEVERITY_WARNING,
                    'title' => 'Telegram notification deliveries are failing',
                    'message' => "{$failed} Telegram delivery attempt(s) failed in the last {$lookbackHours} hour(s).",
                    'module' => 'system',
                    'url' => '/settings',
                    'data' => ['failed_deliveries' => $failed, 'lookback_hours' => $lookbackHours],
                ]);
            }
        }

        return $seen;
    }

    private function patientName($patient): string
    {
        if ($patient === null) {
            return 'Unknown patient';
        }

        $name = trim(($patient->first_name ?? '').' '.($patient->last_name ?? ''));

        return $name !== '' ? $name : ('Patient '.($patient->patient_number ?? $patient->id));
    }
}
