# Local authentication-code testing

Local authentication flows do not require SMTP or a real email provider. The application stores one-time codes as hashes and displays a development-only instruction on the corresponding verification page.

After starting registration, run:

```text
php artisan auth:local-otp registration person@example.test
```

After requesting password recovery, run:

```text
php artisan auth:local-otp recovery person@example.test
```

The command is available in local development, or in the isolated MySQL test database when `APP_ENV=testing`. It generates a fresh six-digit code, invalidates any previous code for that flow, and prints the new code only to the terminal. Codes are not returned by an API, rendered in the browser, or written to application logs. Each code expires after five minutes.

Production email delivery remains a deployment configuration responsibility. Never enable local mode or debug output in production.
