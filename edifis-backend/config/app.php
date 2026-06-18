<?php

return [

    'name' => env('APP_NAME', 'EDIFIS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Africa/Douala',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'file'),
    ],
    'edifis' => [
        'mode' => env('EDIFIS_MODE', 'local'),
        'school_code' => env('EDIFIS_SCHOOL_CODE', 'pssnkwen'),
        'node_id' => env('EDIFIS_NODE_ID', 'node-pssnkwen-01'),
        'currency' => env('CURRENCY', 'XAF'),
        'sanctum_token_ttl_minutes' => (int) env('SANCTUM_TOKEN_TTL_MINUTES', 120),
        'sanctum_offline_grace_minutes' => (int) env('SANCTUM_OFFLINE_GRACE_MINUTES', 720),
        'revocation_pull_on_sync' => (bool) env('REVOCATION_LIST_PULL_ON_SYNC', true),
    ],
    'sync' => [
        'cloud_base_url' => env('SYNC_CLOUD_BASE_URL', 'https://pssnkwen.edifis.cm/api'),
        'delta_max_batch' => (int) env('SYNC_DELTA_MAX_BATCH', 500),
        'rate_limit_per_minute' => (int) env('SYNC_RATE_LIMIT_PER_MINUTE', 120),
        'backoff_base_seconds' => (int) env('SYNC_BACKOFF_BASE_SECONDS', 2),
        'priority_lane' => env('SYNC_PRIORITY_LANE', 'accountability'),
    ],
    'monitoring' => [
        'endpoint' => env('MONITORING_ENDPOINT', 'https://edifis.cm/api/monitoring/node-status'),
        'report_interval_seconds' => (int) env('MONITORING_REPORT_INTERVAL_SECONDS', 300),
    ],

];
