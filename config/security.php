<?php

return [
    'headers' => [
        'csp' => [
            'enabled' => env('SECURITY_CSP_ENABLED', true),
            'report_only' => env('SECURITY_CSP_REPORT_ONLY', true),
            'directives' => [
                "default-src 'self'",
                "base-uri 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "form-action 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data: https://fonts.gstatic.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self'",
            ],
        ],
    ],

    'backup' => [
        'enabled' => env('SECURITY_BACKUP_ENABLED', false),
        'disk' => env('SECURITY_BACKUP_DISK', 'local'),
        'path' => env('SECURITY_BACKUP_PATH', 'private/backups'),
        'mysqldump_binary' => env('SECURITY_MYSQLDUMP_BINARY', 'mysqldump'),
        'retention_days' => (int) env('SECURITY_BACKUP_RETENTION_DAYS', 14),
        'schedule_time' => env('SECURITY_BACKUP_SCHEDULE_TIME', '02:30'),
    ],
];
