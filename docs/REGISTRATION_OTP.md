# Registration OTP lifecycle

Registration requests are kept outside `users` until email verification succeeds.

- `pending_registrations` stores the normalized email, name, Laravel-hashed password, and a 24-hour cleanup expiry.
- `email_verification_otps` stores the OTP hash, five-minute expiry, attempt count, and resend timestamp. Plaintext OTPs are not stored in any environment or returned by the API.
- Registration codes expire after five minutes, allow up to five incorrect attempts, and use a 60-second resend cooldown. Pending registration data expires after 24 hours.
- In `APP_ENV=local`, registration and resend suppress email delivery. After a registration request succeeds, issue a fresh code with `php artisan auth:local-otp registration <email>`; for recovery, use `php artisan auth:local-otp recovery <email>`. The command prints the code only to its terminal, invalidates any previously issued code for that flow, and is restricted to local development or an isolated MySQL test database. Never copy the code into logs or shared output.
- Outside `local`, the application attempts delivery through the configured Laravel Mail transport. It sends only after creating/replacing the stored OTP hash; a mail delivery failure expires that hash and logs only the exception type.
- Verification locks the OTP and pending rows in one database transaction. A valid code creates one verified `user` account, then deletes both temporary rows before the transaction commits. Invalid, expired, or reused codes never create an account.
- `auth:purge-expired-registrations` deletes pending registrations whose 24-hour expiry has passed, their associated registration OTPs, and orphaned registration OTPs older than 24 hours. It does not query or modify `users`.
- `auth:purge-expired-password-recovery-otps` deletes unverified recovery OTP records after their OTP expiry and verified recovery records after their recovery authorization expiry. It does not clean registration records.

## Scheduler deployment

The Laravel schedule runs both `auth:purge-expired-registrations` and `auth:purge-expired-password-recovery-otps` every five minutes. It also runs `planora:notify-tasks-due-soon` daily at 08:00 with overlap prevention. When `BACKUP_SCHEDULE_ENABLED` is true, the configured daily full backup and 03:15 retention-pruning task are scheduled as well; see [Backup & Disaster Recovery](BACKUPS_AND_DISASTER_RECOVERY.md) for their configuration and operational requirements. Keep Laravel's scheduler running in production:

**Linux / cron** (add once per deployment user):

```cron
* * * * * cd /path/to/Planora && php artisan schedule:run >> /dev/null 2>&1
```

**Windows / Task Scheduler:** create a task that runs once per minute, with the Planora project as its working directory and the action:

```text
Program: <full path to php.exe>
Arguments: artisan schedule:run
Start in: <Planora project directory>
```

Use the PHP executable and project path installed on the server. The scheduler must run for automatic cleanup and due-task reminders. Without it, expired rows remain stored until each cleanup command is run manually. Run the registration cleanup:

```sh
php artisan auth:purge-expired-registrations
```

Run the separate password-recovery cleanup:

```sh
php artisan auth:purge-expired-password-recovery-otps
```
