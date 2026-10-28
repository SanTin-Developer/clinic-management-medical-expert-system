<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alert generation
    |--------------------------------------------------------------------------
    |
    | Every threshold used by the alert detectors lives here so operations can
    | tune noise without changing application code. The values are intentionally
    | conservative: an alert is only raised when the condition is durable, and
    | alerts are resolved automatically as soon as the condition clears.
    |
    */

    'reopen' => [
        // A manually resolved alert stays quiet for this long while the
        // underlying condition persists. System-resolved alerts reopen as soon
        // as the condition returns.
        'manual_snooze_minutes' => (int) env('ALERTS_MANUAL_REOPEN_MINUTES', 1440),
    ],

    'inventory' => [
        // Used only when a medicine has no explicit reorder level configured.
        'default_reorder_level' => (int) env('ALERTS_INVENTORY_REORDER_LEVEL', 20),
        'expiry_warning_days' => (int) env('ALERTS_INVENTORY_EXPIRY_DAYS', 30),
    ],

    'inpatient_billing' => [
        'outstanding_warning' => (float) env('ALERTS_INPATIENT_OUTSTANDING_WARNING', 500),
        'outstanding_critical' => (float) env('ALERTS_INPATIENT_OUTSTANDING_CRITICAL', 2000),
        'unbilled_warning' => (float) env('ALERTS_INPATIENT_UNBILLED_WARNING', 100),
        'unbilled_age_days' => (int) env('ALERTS_INPATIENT_UNBILLED_AGE_DAYS', 3),
        // A failed inpatient billing run is only surfaced for this long, so a
        // stale failure does not alert forever.
        'billing_run_lookback_days' => (int) env('ALERTS_INPATIENT_BILLING_RUN_LOOKBACK_DAYS', 7),
    ],

    'appointments' => [
        'lookback_days' => (int) env('ALERTS_APPOINTMENT_LOOKBACK_DAYS', 14),
    ],

    'staff' => [
        'license_expiry_days' => (int) env('ALERTS_STAFF_LICENSE_EXPIRY_DAYS', 30),
    ],

    'attendance' => [
        'lookback_days' => (int) env('ALERTS_ATTENDANCE_LOOKBACK_DAYS', 14),
        'absence_threshold' => (int) env('ALERTS_ATTENDANCE_ABSENCE_THRESHOLD', 3),
        'late_threshold' => (int) env('ALERTS_ATTENDANCE_LATE_THRESHOLD', 3),
        'late_minutes' => (int) env('ALERTS_ATTENDANCE_LATE_MINUTES', 30),
        'flag_missing_clock_out' => (bool) env('ALERTS_ATTENDANCE_MISSING_CLOCK_OUT', true),
        // A published shift whose start time passed this long ago, with no
        // attendance record, is flagged as "expected staff not checked in".
        'no_checkin_grace_minutes' => (int) env('ALERTS_ATTENDANCE_NO_CHECKIN_GRACE_MINUTES', 60),
    ],

    'clinical' => [
        'task_grace_minutes' => (int) env('ALERTS_CLINICAL_TASK_GRACE_MINUTES', 30),
        'task_critical_hours' => (int) env('ALERTS_CLINICAL_TASK_CRITICAL_HOURS', 24),
        'medication_grace_minutes' => (int) env('ALERTS_CLINICAL_MEDICATION_GRACE_MINUTES', 60),
    ],

    'system' => [
        'failed_jobs_threshold' => (int) env('ALERTS_SYSTEM_FAILED_JOBS', 1),
        'queue_backlog_threshold' => (int) env('ALERTS_SYSTEM_QUEUE_BACKLOG', 500),
        'disk_free_percent_threshold' => (int) env('ALERTS_SYSTEM_DISK_FREE_PERCENT', 10),
        // Telegram delivery failures are surfaced for this long after they
        // happen; older failures are treated as historical.
        'telegram_failure_lookback_hours' => (int) env('ALERTS_SYSTEM_TELEGRAM_FAILURE_LOOKBACK_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram group delivery
    |--------------------------------------------------------------------------
    |
    | Delivery is deliberately opt-in and limited to categories that never carry
    | patient-identifying data. The service also refuses any alert that has a
    | patient_id, regardless of configuration, and never includes amounts or
    | URLs in the Telegram copy.
    |
    */

    'telegram' => [
        'enabled' => (bool) env('ALERTS_TELEGRAM_ENABLED', false),
        'categories' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ALERTS_TELEGRAM_CATEGORIES', 'system')),
        ))),
        'minimum_severity' => env('ALERTS_TELEGRAM_MIN_SEVERITY', 'critical'),
    ],

];
