<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'path' => trim(env('BACKUP_PATH', 'planora'), '/'),
    'work_path' => storage_path('app/private/backup-work'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'max_archive_bytes' => (int) env('BACKUP_MAX_ARCHIVE_BYTES', 10737418240),
    'schedule_enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
    'schedule_at' => env('BACKUP_SCHEDULE_AT', '02:00'),
    'retention' => [
        'daily_days' => (int) env('BACKUP_KEEP_DAILY_DAYS', 7),
        'weekly_weeks' => (int) env('BACKUP_KEEP_WEEKLY_WEEKS', 4),
        'monthly_months' => (int) env('BACKUP_KEEP_MONTHLY_MONTHS', 6),
    ],
];
