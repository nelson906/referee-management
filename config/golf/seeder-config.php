<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Golf Seeder Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione centrale per tutti i seeder del sistema Golf.
    | Modifica questi valori per personalizzare la generazione dei dati.
    |
    */

    'general' => [
        'environment' => env('APP_ENV', 'local'),
        'seed_production' => env('GOLF_SEED_PRODUCTION', false),
        'default_password' => env('GOLF_DEFAULT_PASSWORD', 'password123'),
        'base_domain' => env('GOLF_BASE_DOMAIN', 'golf.it'),
        'timezone' => env('APP_TIMEZONE', 'Europe/Rome'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zone Configuration
    |--------------------------------------------------------------------------
    */
    'zones' => [
        'count' => 7,
        'auto_create_national_zone' => false,
        'zone_definitions' => [
            [
                'name' => 'Piemonte-Valle d\'Aosta',
                'code' => 'SZR1',
                'description' => 'Zona Nord-Ovest: Piemonte e Valle d\'Aosta',
                'primary_city' => 'Torino',
                'area_codes' => ['011', '0125', '0165'],
                'provinces' => ['TO', 'AO', 'BI', 'CN', 'AL', 'NO', 'VC', 'VB']
            ],
            [
                'name' => 'Lombardia',
                'code' => 'SZR2',
                'description' => 'Zona Nord: Lombardia',
                'primary_city' => 'Milano',
                'area_codes' => ['02', '039', '035', '030'],
                'provinces' => ['MI', 'MB', 'BG', 'BS', 'CO', 'CR', 'LC', 'LO', 'MN', 'PV', 'SO', 'VA']
            ],
            [
                'name' => 'Veneto-Trentino',
                'code' => 'SZR3',
                'description' => 'Zona Nord-Est: Veneto e Trentino Alto Adige',
                'primary_city' => 'Venezia',
                'area_codes' => ['041', '045', '049', '0461'],
                'provinces' => ['VE', 'VR', 'PD', 'VI', 'TV', 'BL', 'RO', 'TN', 'BZ']
            ],
            [
                'name' => 'Emilia Romagna-Marche',
                'code' => 'SZR4',
                'description' => 'Zona Centro-Nord: Emilia Romagna e Marche',
                'primary_city' => 'Bologna',
                'area_codes' => ['051', '059', '0541', '071'],
                'provinces' => ['BO', 'MO', 'PR', 'RE', 'PC', 'FE', 'FC', 'RN', 'RA', 'AN', 'AP', 'FM', 'MC', 'PU']
            ],
            [
                'name' => 'Toscana-Umbria',
                'code' => 'SZR5',
                'description' => 'Zona Centro: Toscana e Umbria',
                'primary_city' => 'Firenze',
                'area_codes' => ['055', '050', '0564', '075'],
                'provinces' => ['FI', 'PI', 'SI', 'AR', 'GR', 'LI', 'LU', 'MS', 'PT', 'PO', 'PG', 'TR']
            ],
            [
                'name' => 'Lazio-Abruzzo-Molise',
                'code' => 'SZR6',
                'description' => 'Zona Centro-Sud: Lazio, Abruzzo e Molise',
                'primary_city' => 'Roma',
                'area_codes' => ['06', '0862', '0874'],
                'provinces' => ['RM', 'LT', 'VT', 'RI', 'FR', 'AQ', 'PE', 'CH', 'TE', 'CB', 'IS']
            ],
            [
                'name' => 'Sud Italia-Sicilia-Sardegna',
                'code' => 'SZR7',
                'description' => 'Zona Sud: Meridione, Sicilia e Sardegna',
                'primary_city' => 'Napoli',
                'area_codes' => ['081', '080', '0925', '070'],
                'provinces' => ['NA', 'SA', 'AV', 'CE', 'BN', 'BA', 'FG', 'BR', 'TA', 'LE', 'BT', 'MT', 'PZ', 'CS', 'CZ', 'KR', 'RC', 'VV', 'PA', 'CT', 'ME', 'AG', 'CL', 'EN', 'RG', 'SR', 'TP', 'CA', 'CI', 'VS', 'NU', 'OG', 'OR', 'SS', 'SU']
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Users Configuration
    |--------------------------------------------------------------------------
    */
    'users' => [
        'admins' => [
            'super_admin_count' => 1,
            'national_admin_count' => 2,
            'zone_admin_per_zone' => 1,
            'default_city' => 'Roma',
            'default_phone_prefix' => '+39'
        ],
        'referees' => [
            'per_zone' => 13,
            'level_distribution' => [
                'aspirante' => 3,
                'primo_livello' => 4,
                'regionale' => 3,
                'nazionale' => 2,
                'internazionale' => 1
            ],
            'active_percentage' => 95,
            'qualification_years_range' => [1, 20]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Tournament Types Configuration
    |--------------------------------------------------------------------------
    */
    'tournament_types' => [
        'zonal' => [
            [
                'name' => 'Gara Sociale',
                'code' => 'SOCIALE',
                'min_referees' => 1,
                'max_referees' => 2,
                'priority_level' => 1,
                'requires_approval' => false
            ],
            [
                'name' => 'Trofeo di Zona',
                'code' => 'TROFEO_ZONA',
                'min_referees' => 1,
                'max_referees' => 3,
                'priority_level' => 2,
                'requires_approval' => true
            ],
            [
                'name' => 'Campionato Zonale',
                'code' => 'CAMP_ZONALE',
                'min_referees' => 2,
                'max_referees' => 4,
                'priority_level' => 3,
                'requires_approval' => true
            ]
        ],
        'national' => [
            [
                'name' => 'Open Nazionale',
                'code' => 'OPEN_NAZ',
                'min_referees' => 2,
                'max_referees' => 4,
                'priority_level' => 4,
                'requires_approval' => true
            ],
            [
                'name' => 'Campionato Italiano',
                'code' => 'CAMP_ITA',
                'min_referees' => 3,
                'max_referees' => 5,
                'priority_level' => 5,
                'requires_approval' => true
            ],
            [
                'name' => 'Major Italiano',
                'code' => 'MAJOR_ITA',
                'min_referees' => 4,
                'max_referees' => 6,
                'priority_level' => 6,
                'requires_approval' => true
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Clubs Configuration
    |--------------------------------------------------------------------------
    */
    'clubs' => [
        'per_zone' => 4,
        'holes_distribution' => [
            9 => 30,   // 30% con 9 buche
            18 => 60,  // 60% con 18 buche
            27 => 10   // 10% con 27 buche
        ],
        'par_range' => [70, 72],
        'course_rating_range' => [68.0, 74.0],
        'slope_rating_range' => [110, 140],
        'founded_year_range' => [1920, 2010],
        'active_percentage' => 100
    ],

    /*
    |--------------------------------------------------------------------------
    | Tournaments Configuration
    |--------------------------------------------------------------------------
    */
    'tournaments' => [
        'per_zone' => [
            'completed' => 5,
            'assigned' => 3,
            'open' => 2,
            'closed' => 1,
            'draft' => 3,
            'scheduled' => 2
        ],
        'national_tournaments' => 3,
        'date_ranges' => [
            'completed' => ['months_ago' => [1, 8]],
            'assigned' => ['days_ahead' => [5, 30]],
            'open' => ['days_ahead' => [20, 45]],
            'closed' => ['days_ahead' => [10, 25]],
            'draft' => ['months_ahead' => [2, 6]],
            'scheduled' => ['months_ahead' => [1, 4]]
        ],
        'entry_fees' => [
            1 => [30, 50],      // Gare sociali
            2 => [50, 80],      // Trofei zona
            3 => [80, 120],     // Campionati zonali
            4 => [120, 180],    // Open nazionali
            5 => [180, 250],    // Campionati italiani
            6 => [250, 400]     // Major italiani
        ],
        'prize_pools' => [
            1 => [500, 1000],
            2 => [1000, 2500],
            3 => [2500, 5000],
            4 => [5000, 10000],
            5 => [10000, 25000],
            6 => [25000, 50000]
        ],
        'featured_percentage' => 20
    ],

    /*
    |--------------------------------------------------------------------------
    | Availabilities Configuration
    |--------------------------------------------------------------------------
    */
    'availabilities' => [
        'response_rate' => 0.7, // 70% arbitri dichiarano
        'availability_rate' => 0.75, // 75% di chi risponde è disponibile
        'note_probability' => 0.4, // 40% aggiunge note
        'travel_required_same_zone' => 0.2, // 20% nella stessa zona
        'travel_required_different_zone' => 0.8, // 80% zone diverse
        'accommodation_if_travel' => 0.6, // 60% se serve viaggio
        'submission_timing' => [
            'early_responders' => 0.3, // 30% risponde subito
            'mid_responders' => 0.5,   // 50% entro metà periodo
            'late_responders' => 0.2   // 20% ultimi giorni
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Assignments Configuration
    |--------------------------------------------------------------------------
    */
    'assignments' => [
        'confirmation_rate' => 0.9, // 90% conferma
        'role_distribution' => [
            'Arbitro' => 50,
            'Direttore Torneo' => 15,
            'Supervisore' => 15,
            'Osservatore' => 10,
            'Assistente' => 10
        ],
        'fee_base_amounts' => [
            'Direttore Torneo' => 200,
            'Supervisore' => 150,
            'Arbitro' => 100,
            'Osservatore' => 80,
            'Assistente' => 60
        ],
        'travel_compensation_ranges' => [
            'same_zone' => [0, 50],
            'different_zone' => [100, 300]
        ],
        'special_instructions_probability' => 0.3,
        'assignment_timing_days_after_deadline' => [1, 5],
        'confirmation_timing_days_after_assignment' => [1, 3]
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Quality Configuration
    |--------------------------------------------------------------------------
    */
    'data_quality' => [
        'enforce_foreign_keys' => true,
        'validate_emails' => true,
        'ensure_unique_codes' => true,
        'check_date_logic' => true,
        'verify_zone_consistency' => true,
        'minimum_data_integrity' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'batch_size' => 100,
        'use_transactions' => true,
        'disable_query_log' => true,
        'chunk_large_operations' => true,
        'optimize_for_speed' => env('GOLF_OPTIMIZE_SPEED', true)
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    */
    'development' => [
        'verbose_output' => env('GOLF_VERBOSE', true),
        'show_progress_bars' => true,
        'validate_after_seeding' => true,
        'create_test_scenarios' => true,
        'generate_sample_queries' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Features
    |--------------------------------------------------------------------------
    */
    'optional_features' => [
        'create_notifications' => true,
        'create_letter_templates' => true,
        'create_system_configs' => false,
        'create_audit_logs' => false,
        'create_file_attachments' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Scenarios
    |--------------------------------------------------------------------------
    */
    'testing_scenarios' => [
        'zone_isolation' => [
            'enabled' => true,
            'test_zone' => 'SZR6',
            'admin_email' => 'admin.szr6@golf.it'
        ],
        'availability_workflow' => [
            'enabled' => true,
            'sample_tournaments' => 2,
            'sample_referees' => 5
        ],
        'assignment_process' => [
            'enabled' => true,
            'closed_tournaments' => 1,
            'min_availabilities' => 3
        ],
        'national_access' => [
            'enabled' => true,
            'admin_email' => 'crc@golf.it'
        ],
        'historical_data' => [
            'enabled' => true,
            'completed_tournaments' => 3,
            'months_back' => 6
        ]
    ]
];
