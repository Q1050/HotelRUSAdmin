<?php

return [
    'scheduler_stale_minutes' => (int) env('HEALTH_SCHEDULER_STALE_MINUTES', 5),
    'lock_health_interval_minutes' => (int) env('LOCK_HEALTH_INTERVAL_MINUTES', 15),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_max_mb' => (int) env('BACKUP_MAX_MB', 1024),
    'backup_stale_hours' => (int) env('BACKUP_STALE_HOURS', 26),
];
