<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ⚙️ GOLF BACKUP SYSTEM CONFIGURATION
    |--------------------------------------------------------------------------
    |
    | Configurazione completa per il sistema di backup Golf Referee Management
    | Integrato con il comando `php artisan golf:backup`
    |
    */

    'backup' => [
        /*
        |--------------------------------------------------------------------------
        | 🔧 Core Settings
        |--------------------------------------------------------------------------
        */
        'enabled' => env('BACKUP_ENABLED', true),
        'name' => env('APP_NAME', 'golf-referee-management'),

        /*
        |--------------------------------------------------------------------------
        | 📁 Storage Configuration
        |--------------------------------------------------------------------------
        */
        'destination' => [
            /*
            | Disk di destinazione per i backup
            | Supportati: 'local', 's3', 'ftp', 'sftp'
            */
            'disks' => [
                env('BACKUP_DISK_PRIMARY', 'backup'),
                // Backup secondario opzionale
                // env('BACKUP_DISK_SECONDARY', 's3'),
            ],

            /*
            | Path di base per i backup
            */
            'filename_prefix' => env('BACKUP_FILENAME_PREFIX', 'golf-backup-'),
            'directory_prefix' => env('BACKUP_DIRECTORY_PREFIX', 'golf-backups/'),
        ],

        /*
        |--------------------------------------------------------------------------
        | 🗄️ Database Configuration
        |--------------------------------------------------------------------------
        */
        'database' => [
            /*
            | Connessioni database da includere nel backup
            */
            'connections' => [
                config('database.default'),
            ],

            /*
            | Tabelle da escludere dal backup
            */
            'exclude_tables' => [
                'activity_log', // Laravel Activity Log (troppo voluminoso)
                'failed_jobs',  // Job falliti (ricostruibili)
                'sessions',     // Sessioni utente (temporanee)
                'cache',        // Cache (ricostruibile)
                'telescope_entries', // Laravel Telescope (se abilitato)
                'telescope_entries_tags',
                'telescope_monitoring',
            ],

            /*
            | Includere anche la struttura del database
            */
            'add_drop_table' => true,
            'single_transaction' => true,
            'lock_tables' => false,
            'compress' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | 📂 File System Configuration
        |--------------------------------------------------------------------------
        */
        'files' => [
            /*
            | Directory da includere nel backup
            */
            'include' => [
                base_path('app'),
                base_path('config'),
                base_path('database/migrations'),
                base_path('database/seeders'),
                base_path('resources'),
                base_path('routes'),
                base_path('public/logos'), // Logo zone e club
                base_path('public/letterheads'), // Carta intestata
                storage_path('app/documents'), // Documenti caricati
                storage_path('app/exports'), // Export generati
            ],

            /*
            | Directory da escludere
            */
            'exclude' => [
                base_path('node_modules'),
                base_path('.git'),
                base_path('vendor'),
                base_path('storage/framework/cache'),
                base_path('storage/framework/sessions'),
                base_path('storage/framework/views'),
                base_path('storage/logs'),
                base_path('storage/app/livewire-tmp'),
            ],

            /*
            | Pattern di file da escludere
            */
            'exclude_patterns' => [
                '*.log',
                '*.cache',
                '.DS_Store',
                'Thumbs.db',
                '*.tmp',
                '*.temp',
            ],

            /*
            | Dimensione massima file singolo (MB)
            */
            'max_file_size' => 50,
        ],

        /*
        |--------------------------------------------------------------------------
        | 🔐 Security & Compression
        |--------------------------------------------------------------------------
        */
        'compression' => [
            'enabled' => env('BACKUP_COMPRESSION', true),
            'method' => 'gzip', // gzip, zip, bzip2
            'level' => 6, // Livello compressione (1-9)
        ],

        'encryption' => [
            'enabled' => env('BACKUP_ENCRYPTION', false),
            'key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
            'method' => 'AES-256-CBC',
        ],

        /*
        |--------------------------------------------------------------------------
        | ⏰ Retention & Cleanup
        |--------------------------------------------------------------------------
        */
        'retention' => [
            /*
            | Politiche di retention automatica
            */
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 12,
            'keep_yearly_backups_for_years' => 3,

            /*
            | Cleanup automatico
            */
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,

            /*
            | Numero massimo di backup da mantenere
            */
            'maximum_number_of_backups' => 50,
        ],

        /*
        |--------------------------------------------------------------------------
        | 📈 Monitoring & Notifications
        |--------------------------------------------------------------------------
        */
        'monitoring' => [
            /*
            | Monitoraggio backup
            */
            'enabled' => env('BACKUP_MONITORING', true),

            /*
            | Numero massimo di giorni senza backup prima di considerarlo un errore
            */
            'maximum_age_in_days' => 1,

            /*
            | Dimensione minima backup per considerarlo valido (MB)
            */
            'minimum_size_in_megabytes' => 1,
        ],

        'notifications' => [
            /*
            | Notifiche backup
            */
            'enabled' => env('BACKUP_NOTIFICATIONS', true),

            /*
            | Eventi per cui inviare notifiche
            */
            'events' => [
                'backup_successful' => true,
                'backup_failed' => true,
                'cleanup_successful' => false,
                'cleanup_failed' => true,
                'healthy_backup_found' => false,
                'unhealthy_backup_found' => true,
            ],

            /*
            | Destinatari notifiche
            */
            'mail' => [
                'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),
                'from' => [
                    'address' => env('MAIL_FROM_ADDRESS'),
                    'name' => env('MAIL_FROM_NAME'),
                ],
            ],

            /*
            | Integrazione Slack (opzionale)
            */
            'slack' => [
                'webhook_url' => env('BACKUP_SLACK_WEBHOOK'),
                'channel' => env('BACKUP_SLACK_CHANNEL', '#monitoring'),
                'username' => 'Golf Backup Bot',
                'icon' => ':floppy_disk:',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 📊 Performance & Limits
        |--------------------------------------------------------------------------
        */
        'performance' => [
            /*
            | Timeout backup (secondi)
            */
            'timeout' => env('BACKUP_TIMEOUT', 3600),

            /*
            | Limite memoria PHP per backup
            */
            'memory_limit' => env('BACKUP_MEMORY_LIMIT', '2G'),

            /*
            | Elaborazione file parallela
            */
            'parallel_processing' => env('BACKUP_PARALLEL', false),
            'max_concurrent_jobs' => 3,

            /*
            | Dimensione massima backup singolo (MB)
            */
            'max_backup_size' => env('BACKUP_MAX_SIZE', 1000),
        ],

        /*
        |--------------------------------------------------------------------------
        | 🎯 Golf-Specific Configuration
        |--------------------------------------------------------------------------
        */
        'golf_specific' => [
            /*
            | Backup seeder data separatamente
            */
            'backup_seeder_data' => true,
            'seeder_data_retention_days' => 90,

            /*
            | Include export reports nel backup
            */
            'include_reports' => true,
            'reports_max_age_days' => 365,

            /*
            | Backup configurazioni specifiche del golf
            */
            'include_zone_configs' => true,
            'include_tournament_templates' => true,
            'include_letterheads' => true,

            /*
            | Esecuzione test di integrità dopo backup
            */
            'run_integrity_check' => env('BACKUP_INTEGRITY_CHECK', true),

            /*
            | Generazione report backup
            */
            'generate_backup_report' => true,
            'backup_report_email' => env('BACKUP_REPORT_EMAIL'),
        ],

        /*
        |--------------------------------------------------------------------------
        | 🚀 Scheduling Configuration
        |--------------------------------------------------------------------------
        */
        'schedule' => [
            /*
            | Frequenza backup automatici
            */
            'frequency' => env('BACKUP_FREQUENCY', 'daily'), // daily, weekly, monthly

            /*
            | Orario esecuzione backup (formato cron)
            */
            'time' => env('BACKUP_TIME', '02:00'), // 2:00 AM

            /*
            | Backup differenziali (solo modifiche)
            */
            'differential_enabled' => env('BACKUP_DIFFERENTIAL', false),
            'differential_frequency' => 'hourly',

            /*
            | Backup completo settimanale
            */
            'full_backup_day' => 'sunday',
            'full_backup_time' => '01:00',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 🔄 Restore Configuration
    |--------------------------------------------------------------------------
    */
    'restore' => [
        /*
        | Impostazioni di ripristino
        */
        'enabled' => env('RESTORE_ENABLED', false), // Disabilitato per sicurezza
        'require_confirmation' => true,
        'backup_before_restore' => true,

        /*
        | Directory temporanea per restore
        */
        'temp_directory' => storage_path('app/restore-temp'),

        /*
        | Timeout restore (secondi)
        */
        'timeout' => env('RESTORE_TIMEOUT', 7200),
    ],

    /*
    |--------------------------------------------------------------------------
    | 🛠️ Development Configuration
    |--------------------------------------------------------------------------
    */
    'development' => [
        /*
        | Impostazioni specifiche per sviluppo
        */
        'enabled' => env('APP_ENV') !== 'production',
        'backup_frequency' => 'manual',
        'keep_only_last_backup' => true,
        'skip_large_files' => true,
        'test_mode' => env('BACKUP_TEST_MODE', false),
    ],
];
