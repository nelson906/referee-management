<?php
return [
    'enabled' => env('BACKUP_ENABLED', true),

    'retention_days' => env('BACKUP_RETENTION_DAYS', 30),

    'notification_emails' => [
        'sysadmin@federgolf.it',
        'backup-alerts@federgolf.it'
    ],

    'cloud_enabled' => env('BACKUP_CLOUD_ENABLED', false),
    'cloud_disk' => env('BACKUP_CLOUD_DISK', 's3'),
    'cloud_path' => env('BACKUP_CLOUD_PATH', 'golf-arbitri-backups'),

    'compression' => [
        'enabled' => true,
        'level' => 6 // 1-9, default 6
    ],

    'verification' => [
        'enabled' => true,
        'method' => 'md5'
    ]
];
