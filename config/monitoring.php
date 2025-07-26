<?php
return [
    'enabled' => env('MONITORING_ENABLED', true),

    'thresholds' => [
        'response_time_ms' => env('MONITOR_RESPONSE_TIME_MS', 1000),
        'error_rate_percent' => env('MONITOR_ERROR_RATE_PERCENT', 5),
        'queue_size' => env('MONITOR_QUEUE_SIZE', 100),
        'disk_usage_percent' => env('MONITOR_DISK_USAGE_PERCENT', 80),
        'memory_usage_percent' => env('MONITOR_MEMORY_USAGE_PERCENT', 85),
        'failed_jobs_count' => env('MONITOR_FAILED_JOBS_COUNT', 10),
        'email_failure_rate' => env('MONITOR_EMAIL_FAILURE_RATE', 10),
    ],

    'alert_recipients' => [
        'sysadmin@federgolf.it',
        'dev-team@federgolf.it',
    ],

    'alert_rate_limit_minutes' => 15,

    'history_retention_hours' => 168, // 7 giorni
];
