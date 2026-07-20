# Production deployment and operations

The application uses Laravel's database queue by default. Run separate workers so guest notifications and lock health checks never delay a web request:

```bash
php artisan queue:work database --queue=notifications,locks,backups,default --tries=4 --timeout=900 --sleep=2 --max-time=3600
```

Manage that command with Supervisor, systemd, a container restart policy, or the hosting provider's worker service. Restart workers after every release with `php artisan queue:restart`.

Run the scheduler continuously with `php artisan schedule:work`, or add Laravel's standard once-per-minute cron entry:

```cron
* * * * * cd /path/to/hotel-checkin && php artisan schedule:run >> /dev/null 2>&1
```

The public `/health` endpoint reports application, database, queue, scheduler, storage, and release-version status. A new deployment may report a scheduler warning until its first minute tick. Keep `/up` for a minimal framework liveness probe and use `/health` for readiness monitoring.

Every five minutes, `operations:monitor` checks each active property's lock freshness, battery levels, and recent mobile-delivery failures. Hotel super administrators configure thresholds, recipient roles, additional email addresses, and alert cooldowns in Security & Audit. Platform administrators see a cross-property attention rollup without tenant payload details.

Old failed queue records and batches are pruned after seven days. Long-term incident history remains available through the security audit log rather than serialized queue payloads.

## Database and file backups

HotelKey creates a daily encrypted, per-property bundle containing tenant-owned database rows and referenced private files. `BACKUP_DISK=local` works for development; production should normally select the configured `s3` disk. Laravel's S3 disk supports AWS S3 and compatible services through `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT`.

The application encryption key protects bundle contents. Keep a protected copy of `APP_KEY` outside the application server—without the matching key, encrypted backups cannot be decrypted. Backups are checksummed and verified after creation and again daily. Verification is deliberately non-destructive and never imports data.

For disaster recovery, first restore into an isolated environment with the same application release and `APP_KEY`, verify the bundle, review its manifest and missing-file list, and only then use a separately reviewed import procedure. The web application intentionally provides no one-click production restore.

Back up both the database and `storage/app` because guest attachments and permitted ID documents live outside the database. For MariaDB/MySQL, a typical encrypted backup workflow begins with:

```bash
mysqldump --single-transaction --routines --triggers --databases hotel_checkin > hotel-checkin.sql
tar -czf hotel-checkin-storage.tar.gz storage/app
```

Supply credentials through a protected MySQL option file or secret manager rather than the command line. Encrypt backups, send them to storage outside the application server, retain them for at least `BACKUP_RETENTION_DAYS`, and test a restore regularly. Schedule backups in the hosting platform rather than the Laravel request process.

## Release checklist

1. Put the app in maintenance mode and deploy the tested artifact.
2. Run `php artisan migrate --force` and `php artisan optimize`.
3. Run `php artisan queue:restart` and verify the worker supervisor restarted it.
4. Verify the scheduler heartbeat and `/health` response.
5. Confirm recent backups and exercise a notification plus a simulator lock sync.
6. Bring the app out of maintenance mode and watch failed jobs in Security & Audit.
