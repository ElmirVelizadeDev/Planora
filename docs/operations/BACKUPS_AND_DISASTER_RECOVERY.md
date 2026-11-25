# Backups and disaster recovery

Planora's administrator backup feature creates encrypted recovery archives for the MySQL database, managed uploads, or both. The feature uses the existing Laravel filesystem abstraction and does not add a database table or an external backup package.

## Storage and encryption

The default `backups` disk stores artifacts under `storage/app/private/backups`, outside the public web root. Configure `BACKUP_DISK` to a private Laravel filesystem disk and `BACKUP_PATH` to a relative directory containing no `.` or `..` segments. Do not point it at the `public` disk or a publicly readable bucket. S3 requires the Laravel Flysystem S3 adapter and a private bucket; it is not installed by default.

Archives are encrypted with AES-256-GCM in authenticated chunks. Set `BACKUP_ENCRYPTION_KEY` to a base64-encoded, randomly generated 32-byte key and store it separately from the application and backups. If it is blank, Planora derives a stable backup key from `APP_KEY`; losing or changing that key makes existing archives unrecoverable. Keep an independently protected copy of the chosen key. Do not put it in source control or expose it through the API.

The database exporter currently supports MySQL/MariaDB InnoDB base tables and reads them from a repeatable-read consistent snapshot. It fails closed if it finds non-InnoDB tables, views, triggers, routines, or scheduled database events rather than silently producing an incomplete or inconsistent backup. File backups contain user avatars, settings branding uploads, and the Planora maintenance-scope metadata file. They do not include `.env`, application code, logs, cache, or arbitrary storage files.

## Configuration

The settings below are in `.env.example`:

| Variable | Default | Purpose |
|---|---:|---|
| `BACKUP_DISK` | `backups` | Configured private Laravel filesystem disk |
| `BACKUP_PATH` | `planora` | Relative artifact and metadata prefix on that disk |
| `BACKUP_ENCRYPTION_KEY` | empty | Optional base64 32-byte dedicated key; blank derives from `APP_KEY` |
| `BACKUP_SCHEDULE_ENABLED` | `true` | Enable daily archive and retention schedule |
| `BACKUP_SCHEDULE_AT` | `02:00` | Daily full backup time in the Laravel application timezone (UTC by default) |
| `BACKUP_KEEP_DAILY_DAYS` | `7` | Daily retention window |
| `BACKUP_KEEP_WEEKLY_WEEKS` | `4` | Weekly retention window |
| `BACKUP_KEEP_MONTHLY_MONTHS` | `6` | Monthly retention window |
| `BACKUP_MAX_ARCHIVE_BYTES` | `10737418240` | Maximum managed upload archive size |

After changing environment configuration, clear/rebuild Laravel's config cache as appropriate for the deployment.

## Scheduling and manual operations

Laravel schedules a full backup daily at `BACKUP_SCHEDULE_AT` and retention pruning daily at 03:15, both with overlap prevention. The application schedule is not a server scheduler: production must run Laravel's scheduler every minute, for example with the standard `schedule:run` cron entry. Confirm the server timezone and `config('app.timezone')`; the example environment uses UTC.

An administrator can create database-only, files-only, or full backups from **Admin → Backups & Recovery**. The equivalent commands are:

```sh
php artisan planora:backup full
php artisan planora:backup database
php artisan planora:backup files
php artisan planora:backups-prune
php artisan schedule:list
```

The command reports success only after archive validation, encryption, storage, and metadata persistence complete. Failures return a generic message; protected application logs contain the operation identifier and diagnostic exception class.

## Recovery procedure

1. Confirm the private backup disk is available and the encryption key matches the key used when the artifact was created.
2. Ensure the MySQL account can create and drop temporary schemas and can rename tables across schemas. Recovery intentionally fails if those privileges or the required MySQL/MariaDB behavior are unavailable.
3. From **Admin → Backups & Recovery**, review the recovery point and confirm its verified state. Restore requires an explicit confirmation and creates a new verified full safety backup first.
4. Database recovery imports into a temporary schema, validates table names and row counts, enters Laravel maintenance mode, and swaps tables. A failed post-swap validation attempts rollback. If rollback itself fails, Planora intentionally remains in maintenance mode and retains the recovery schema; inspect the server logs and database state before manually bringing the app back up. Keep database backups and verify application behavior after recovery.
5. File recovery restores only the managed file paths listed above. It overlays archived files and does not delete files created after the backup.
6. Confirm the site is available, inspect protected logs, and retain the safety backup until the recovery is validated.

Do not test restore against a production database. Use an isolated disposable MySQL/MariaDB instance with a dedicated test account and schemas. The automated feature tests use fake storage and mocked database exporters/restorers; they do not prove cross-schema restore behavior on every server version or hosting configuration. A controlled QA run on MySQL 8.0.30 used a disposable `planora_backup_qa` database to verify encrypted database backup, full database and managed-file restore, rollback after an injected post-swap validation failure, corrupted-archive rejection, and safety-backup retention. This is evidence for that isolated environment only; repeat the procedure against a disposable instance matching the deployment target before relying on recovery there.

## API and access

All routes are under `/api/v1/admin/backups` and require authenticated, active Admin access. The API supports listing/filtering/pagination, creation, metadata detail, encrypted download, deletion, and restore. Delete and restore confirmations must match `DELETE <backup-uuid>` or `RESTORE <backup-uuid>`, respectively. Moderator access is intentionally not granted. API resources expose safe metadata only; artifact keys, checksums, and encryption details are not returned. See [API documentation](../api/API_DOCUMENTATION.md) for the endpoint and validation reference.

## Limitations and operational checks

- Archives are synchronous and can take time for large databases; configure web request timeouts accordingly or use the Artisan command for large operations.
- Database backup and restore require MySQL or MariaDB. Views, triggers, routines, and events are rejected. Validate this against the actual database before enabling scheduled backups.
- Only the managed upload locations documented above are included; other uploads or local files require a separate deployment-level backup strategy.
- File restore overlays files and does not remove newer unlisted files.
- Backups and restore safety points consume storage. Retention pruning runs daily and always retains the newest verified recovery point.
- Restore requires CREATE/DROP DATABASE and cross-schema table rename privileges, as well as Laravel maintenance-mode file access.
- `schedule:list` verifies the configured Laravel schedule only. The host's scheduler process/cron and external storage availability must be verified in the deployment environment.
- The default cache and maintenance file configuration is local to one host. For a multi-node deployment, use shared cache/lock infrastructure and shared Laravel maintenance storage, and run one coordinated scheduler; otherwise concurrent operations or maintenance state may not be coordinated across nodes.
- Run focused tests with `php artisan test tests/Feature/Admin/Backups`; run the application suite with `php artisan test` and build the frontend with `npm run build`.
