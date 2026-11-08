# Registration OTP lifecycle

Registration requests are kept outside `users` until email verification succeeds.

- `pending_registrations` stores the normalized email, name, Laravel-hashed password, and a 24-hour cleanup expiry.
- `email_verification_otps` is a separate table containing the OTP hash, five-minute expiry, attempt count, and resend timestamp. In `APP_ENV=local`, it also stores the six-digit code in `otp_code` so developers can retrieve it locally without sending email. The code is never included in the API response or model serialization. Outside `local`, `otp_code` is null and only the hash is stored; the existing email transport handles delivery.
- For local registration, read `otp_code` from `email_verification_otps` by the submitted email. Resending after the 60-second cooldown replaces both the hash and readable local code, so the previous code no longer verifies.
- Verification locks the OTP and pending rows in one database transaction. A valid code creates one verified `user` account, then deletes both temporary rows before the transaction commits. Invalid, expired, or reused codes never create an account.
- The cleanup command deletes expired pending rows and corresponding OTP rows, plus orphaned OTP rows older than 24 hours. It does not query or modify `users`.

## Scheduler deployment

The Laravel schedule runs `auth:purge-expired-registrations` every five minutes. Keep Laravel's scheduler running in production:

**Linux / cron** (add once per deployment user):

```cron
* * * * * cd /path/to/Planora && php artisan schedule:run >> /dev/null 2>&1
```

**Windows / Task Scheduler:** create a task that runs once per minute, with the Planora project as its working directory and the action:

```text
Program: C:\laragon\bin\php\php-8.x\php.exe
Arguments: artisan schedule:run
Start in: C:\laragon\www\Planora
```

Use the PHP executable and project path installed on the server. The scheduler must run for automatic cleanup; without it, expired rows remain stored until the Artisan command is run manually:

```sh
php artisan auth:purge-expired-registrations
```
