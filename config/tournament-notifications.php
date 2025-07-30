<?php

return [
    'email' => [
        'enabled' => env('TOURNAMENT_NOTIFICATIONS_ENABLED', true),
        'queue' => env('TOURNAMENT_NOTIFICATIONS_QUEUE', 'emails'),
        'send_delay' => 2,
        'timeout' => 30,
        'max_retries' => 3,
        'from' => [
            'address' => env('TOURNAMENT_NOTIFICATIONS_FROM', 'arbitri@federgolf.it'),
            'name' => env('TOURNAMENT_NOTIFICATIONS_FROM_NAME', 'Sistema Gestione Arbitri Golf')
        ],
    ],

    'templates' => [
        'club' => [
            'default' => 'club_assignment_standard',
            'available' => [
                'club_assignment_standard' => 'Standard - Assegnazione Arbitri',
                'club_assignment_detailed' => 'Dettagliato - Con istruzioni complete',
                'club_assignment_minimal' => 'Minimale - Solo nomi e contatti'
            ]
        ],
        'referee' => [
            'default' => 'referee_assignment_formal',
            'available' => [
                'referee_assignment_formal' => 'Formale - Convocazione ufficiale',
                'referee_assignment_friendly' => 'Cordiale - Tono amichevole',
                'referee_assignment_detailed' => 'Dettagliato - Con tutte le info'
            ]
        ],
        'institutional' => [
            'default' => 'institutional_report_standard',
            'available' => [
                'institutional_report_standard' => 'Standard - Report assegnazione',
                'institutional_report_detailed' => 'Dettagliato - Con statistiche',
                'institutional_report_minimal' => 'Minimale - Solo comunicazione'
            ]
        ]
    ],

    'features' => [
        'new_system_enabled' => env('TOURNAMENT_NOTIFICATIONS_NEW_SYSTEM', true),
        'legacy_fallback' => env('TOURNAMENT_NOTIFICATIONS_LEGACY_FALLBACK', false),
        'auto_migrate_legacy' => true,
    ],

    'security' => [
        'rate_limit' => 30,
        'sandbox_mode' => env('TOURNAMENT_NOTIFICATIONS_SANDBOX', false),
    ]
];
