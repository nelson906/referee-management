<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 🏌️ GOLF REFEREE MANAGEMENT SYSTEM CONFIGURATION
    |--------------------------------------------------------------------------
    |
    | Configurazione principale per il sistema di gestione arbitri Golf
    |
    */

    'app' => [
        'name' => env('GOLF_APP_NAME', 'Golf Referee Management'),
        'version' => env('GOLF_APP_VERSION', '1.0.0'),
        'environment' => env('APP_ENV', 'production'),
        'timezone' => env('APP_TIMEZONE', 'Europe/Rome'),
        'locale' => 'it',
    ],

    /*
    |--------------------------------------------------------------------------
    | 🛡️ Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Super Admin IP Whitelist
        'super_admin_ip_whitelist' => array_filter(explode(',', env('GOLF_SUPER_ADMIN_IPS', ''))),

        // Time restrictions for super admin access
        'super_admin_time_restrictions' => [
            'enabled' => env('GOLF_SUPER_ADMIN_TIME_RESTRICT', false),
            'start_hour' => env('GOLF_SUPER_ADMIN_START_HOUR', 8),
            'end_hour' => env('GOLF_SUPER_ADMIN_END_HOUR', 20),
        ],

        // Security alerts
        'alerts' => [
            'webhook_url' => env('GOLF_SECURITY_WEBHOOK_URL'),
            'email' => env('GOLF_SECURITY_EMAIL'),
            'slack_webhook' => env('GOLF_SLACK_WEBHOOK'),
        ],

        // CSP Configuration
        'csp_report_uri' => env('GOLF_CSP_REPORT_URI'),
        'expect_ct_url' => env('GOLF_EXPECT_CT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 📧 Notification System
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Default notification settings
        'default_from' => [
            'email' => env('GOLF_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
            'name' => env('GOLF_FROM_NAME', 'Golf Referee Management'),
        ],

        // Notification types and their settings
        'types' => [
            'assignment' => [
                'enabled' => true,
                'queue' => 'notifications',
                'retry_attempts' => 3,
                'retry_delay' => 300, // seconds
            ],
            'availability_reminder' => [
                'enabled' => true,
                'queue' => 'notifications',
                'retry_attempts' => 2,
                'retry_delay' => 600,
            ],
            'tournament_update' => [
                'enabled' => true,
                'queue' => 'notifications',
                'retry_attempts' => 3,
                'retry_delay' => 300,
            ],
            'system_alert' => [
                'enabled' => true,
                'queue' => 'high-priority',
                'retry_attempts' => 5,
                'retry_delay' => 120,
            ],
        ],

        // Rate limiting
        'rate_limiting' => [
            'enabled' => env('GOLF_NOTIFICATION_RATE_LIMIT', true),
            'max_per_minute' => env('GOLF_NOTIFICATION_RATE_LIMIT_PER_MINUTE', 60),
            'max_per_hour' => env('GOLF_NOTIFICATION_RATE_LIMIT_PER_HOUR', 500),
        ],

        // Email templates
        'templates' => [
            'default_letterhead' => env('GOLF_DEFAULT_LETTERHEAD_ID'),
            'use_zone_letterhead' => true,
            'fallback_to_global' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 📊 Statistics and Monitoring
    |--------------------------------------------------------------------------
    */
    'statistics' => [
        // Cache settings
        'cache_ttl' => env('GOLF_STATS_CACHE_TTL', 900), // 15 minutes
        'enable_cache' => env('GOLF_STATS_ENABLE_CACHE', true),

        // Data retention
        'data_retention' => [
            'statistics' => env('GOLF_STATS_RETENTION_DAYS', 365),
            'logs' => env('GOLF_LOGS_RETENTION_DAYS', 90),
            'notifications' => env('GOLF_NOTIFICATIONS_RETENTION_DAYS', 180),
        ],

        // Export limits
        'export_limits' => [
            'max_rows' => env('GOLF_EXPORT_MAX_ROWS', 10000),
            'chunk_size' => env('GOLF_EXPORT_CHUNK_SIZE', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 🏌️ Tournament Management
    |--------------------------------------------------------------------------
    */
    'tournaments' => [
        // Default settings
        'default_availability_deadline_days' => env('GOLF_DEFAULT_DEADLINE_DAYS', 7),
        'auto_close_after_days' => env('GOLF_AUTO_CLOSE_TOURNAMENTS', 1),

        // Assignment settings
        'assignment' => [
            'auto_assign_enabled' => env('GOLF_AUTO_ASSIGN', false),
            'min_referees_per_tournament' => env('GOLF_MIN_REFEREES', 1),
            'max_referees_per_tournament' => env('GOLF_MAX_REFEREES', 10),
        ],

        // Validation rules
        'validation' => [
            'min_notice_days' => env('GOLF_MIN_NOTICE_DAYS', 3),
            'max_future_months' => env('GOLF_MAX_FUTURE_MONTHS', 12),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 👨‍💼 Referee Management
    |--------------------------------------------------------------------------
    */
    'referees' => [
        // Referee levels
        'levels' => [
            'aspirante' => 'Aspirante Arbitro',
            'zonale' => 'Arbitro Zonale',
            'regionale' => 'Arbitro Regionale',
            'nazionale' => 'Arbitro Nazionale',
            'internazionale' => 'Arbitro Internazionale',
        ],

        // Performance tracking
        'performance' => [
            'track_assignments' => true,
            'track_availability_rate' => true,
            'track_feedback' => true,
        ],

        // Auto-deactivation
        'auto_deactivate' => [
            'enabled' => env('GOLF_AUTO_DEACTIVATE_REFEREES', false),
            'inactive_months' => env('GOLF_REFEREE_INACTIVE_MONTHS', 12),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 🌍 Zone Management
    |--------------------------------------------------------------------------
    */
    'zones' => [
        // Zone hierarchy
        'enable_hierarchy' => env('GOLF_ZONE_HIERARCHY', true),
        'max_depth' => env('GOLF_ZONE_MAX_DEPTH', 3),

        // Default settings
        'auto_create_letterheads' => env('GOLF_AUTO_CREATE_LETTERHEADS', true),
        'inherit_global_settings' => env('GOLF_INHERIT_GLOBAL_SETTINGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 💾 Backup Configuration
    |--------------------------------------------------------------------------
    */
    'backup' => [
        // Include in backup configuration from backup.php
        'enabled' => env('BACKUP_ENABLED', true),
        'frequency' => env('BACKUP_FREQUENCY', 'daily'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
        'compress' => env('BACKUP_COMPRESSION', true),
        'encrypt' => env('BACKUP_ENCRYPTION', false),

        // Golf-specific backup settings
        'include_letterheads' => true,
        'include_documents' => true,
        'include_exports' => true,
        'backup_seeder_data' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 🔌 Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        // External APIs
        'external_apis' => array_filter([
            env('GOLF_EXTERNAL_API_1'),
            env('GOLF_EXTERNAL_API_2'),
        ]),

        // FIG Integration
        'fig_integration' => [
            'enabled' => env('GOLF_FIG_INTEGRATION', false),
            'api_url' => env('GOLF_FIG_API_URL'),
            'api_key' => env('GOLF_FIG_API_KEY'),
        ],

        // Email service providers
        'email_providers' => [
            'primary' => env('MAIL_MAILER', 'smtp'),
            'backup' => env('GOLF_BACKUP_MAILER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 🎨 UI/UX Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        // Theme settings
        'theme' => env('GOLF_THEME', 'default'),
        'dark_mode' => env('GOLF_DARK_MODE', false),

        // Pagination
        'pagination' => [
            'per_page' => env('GOLF_PAGINATION_PER_PAGE', 15),
            'max_per_page' => env('GOLF_PAGINATION_MAX_PER_PAGE', 100),
        ],

        // Date formats
        'date_format' => env('GOLF_DATE_FORMAT', 'd/m/Y'),
        'datetime_format' => env('GOLF_DATETIME_FORMAT', 'd/m/Y H:i'),
        'time_format' => env('GOLF_TIME_FORMAT', 'H:i'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 📱 Mobile App Settings
    |--------------------------------------------------------------------------
    */
    'mobile' => [
        'enabled' => env('GOLF_MOBILE_ENABLED', false),
        'api_version' => env('GOLF_MOBILE_API_VERSION', 'v1'),
        'push_notifications' => env('GOLF_PUSH_NOTIFICATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | 🧪 Development & Testing
    |--------------------------------------------------------------------------
    */
    'development' => [
        // Seeder settings
        'seeder' => [
            'create_sample_data' => env('GOLF_CREATE_SAMPLE_DATA', false),
            'sample_users_count' => env('GOLF_SAMPLE_USERS', 50),
            'sample_tournaments_count' => env('GOLF_SAMPLE_TOURNAMENTS', 20),
        ],

        // Debug settings
        'debug' => [
            'log_sql_queries' => env('GOLF_LOG_SQL', false),
            'log_notifications' => env('GOLF_LOG_NOTIFICATIONS', true),
            'mock_external_apis' => env('GOLF_MOCK_APIS', false),
        ],

        // Testing
        'testing' => [
            'disable_notifications' => env('GOLF_TESTING_DISABLE_NOTIFICATIONS', true),
            'use_test_database' => env('GOLF_USE_TEST_DB', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 📈 Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Query optimization
        'eager_load_relationships' => env('GOLF_EAGER_LOAD', true),
        'use_query_cache' => env('GOLF_QUERY_CACHE', true),

        // File uploads
        'max_upload_size' => env('GOLF_MAX_UPLOAD_SIZE', '10M'),
        'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'],

        // Session settings
        'session_lifetime' => env('GOLF_SESSION_LIFETIME', 480), // 8 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | 🌐 Localization
    |--------------------------------------------------------------------------
    */
    'localization' => [
        'default_locale' => 'it',
        'fallback_locale' => 'en',
        'available_locales' => ['it', 'en'],
        'timezone' => 'Europe/Rome',
    ],
];
