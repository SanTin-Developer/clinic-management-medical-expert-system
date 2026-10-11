<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report Caching
    |--------------------------------------------------------------------------
    |
    | Aggregate report endpoints recompute the same counts and groupings on
    | every request. When enabled, JSON report payloads are cached for `ttl`
    | seconds. Disable for always-fresh data.
    |
    */

    'cache' => [
        'enabled' => (bool) env('REPORTS_CACHE_ENABLED', true),
        'ttl' => (int) env('REPORTS_CACHE_TTL', 300),
    ],

];
