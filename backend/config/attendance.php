<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clinic Timezone
    |--------------------------------------------------------------------------
    |
    | Shift boundaries (start, clock-in cutoff, end) are authored in the
    | clinic's local time. Timestamps are still stored in UTC (the Laravel
    | application timezone); this value is used to translate the shift
    | template times into UTC before any lateness or window calculation.
    |
    */

    'timezone' => env('CLINIC_TIMEZONE', 'Asia/Phnom_Penh'),

    /*
    |--------------------------------------------------------------------------
    | Rotating QR Token Lifetime
    |--------------------------------------------------------------------------
    |
    | The number of seconds a single QR code stays valid before the admin
    | display requests the next one from the server. Validation is always
    | performed against server time.
    |
    */

    'qr_ttl' => (int) env('ATTENDANCE_QR_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Session Dashboard Poll Interval
    |--------------------------------------------------------------------------
    |
    | The admin session dashboard refreshes through polling (the Reverb
    | realtime server is optional). This is the documented refresh interval
    | in seconds. Upgrading to broadcasting only changes the transport.
    |
    */

    'dashboard_poll_seconds' => (int) env('ATTENDANCE_POLL_SECONDS', 3),

    /*
    |--------------------------------------------------------------------------
    | Lateness Display Bands
    |--------------------------------------------------------------------------
    |
    | Actual lateness is always stored as raw minutes. These bands are only a
    | disciplinary/display label derived from the raw minutes.
    |
    */

    'late_bands' => [
        ['min' => 1, 'max' => 10, 'label' => 'Late 10'],
        ['min' => 11, 'max' => 20, 'label' => 'Late 20'],
        ['min' => 21, 'max' => 29, 'label' => 'Late 30'],
    ],

];
