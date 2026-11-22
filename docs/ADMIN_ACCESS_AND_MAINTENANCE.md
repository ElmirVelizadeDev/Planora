# Administrator access and maintenance

## Administrator entry path

This guide is for operators configuring the protected administrator sign-in route and the Planora maintenance controls.

`PLANORA_ADMIN_ENTRY_PATH` configures the server-side administrator sign-in URL. It is read through `config/planora.php` and registered only as Laravel web routes in `routes/web.php`. The value must be URL-safe and at least 48 characters; production boot fails if it is missing or invalid. The application does not place the path in Vue routes, Vite variables, public HTML, or API response data.

Generate a value with a cryptographically secure random generator, for example:

```sh
php -r "echo 'entry-'.bin2hex(random_bytes(32)), PHP_EOL;"
```

Set the generated value as `PLANORA_ADMIN_ENTRY_PATH` in the ignored local `.env` file or in the production secret manager. Do not commit the value or add a `VITE_` prefix. The local development configuration must also use the intended `APP_URL` and web server host.

The GET route serves the normal Vite shell with a server-rendered `data-planora-view="admin-auth"` marker. `App.vue` renders the dedicated administrator sign-in screen only for that response. The frontend posts to its current URL, so the path is not embedded in the JavaScript bundle. The POST route uses the `web` middleware stack, including session and CSRF middleware, and the existing `auth-login` rate limiter.

Successful administrator sign-in uses the existing `web` guard and `User` record. Credentials, the database role, active status, and verified email are checked on the server. The controller regenerates the session after authentication, returns the existing `UserResource`, and redirects to `/admin/dashboard`. A valid non-admin account is rejected. The entry page and login response are marked private and non-cacheable, use `Referrer-Policy: no-referrer`, and login audit records replace the path with `[protected-admin-entry]`.

Administrators can optionally enroll authenticator-based MFA from **Admin → Settings → Security**. MFA-enabled password login creates a five-minute challenge in the first-party session; successful TOTP or single-use recovery-code verification completes login. Sensitive Admin actions that declare step-up middleware require a separate purpose-bound verification grant. See [authentication and security](AUTHENTICATION_SECURITY.md) and [API documentation](API_DOCUMENTATION.md) for behavior and endpoints.

## Maintenance modes

Planora stores three independent flags in `MaintenanceScopeStore`:

| Scope | Normal routes affected | Administrator behavior |
| --- | --- | --- |
| `public_website` | Home, legal pages, regular auth pages and regular auth API operations | Secret entry remains available; administration remains available |
| `authentication` | Regular login, registration, OTP verification/resend, and password recovery | Secret entry remains available; administration remains available |
| `dashboard_workspace` | Authenticated user and moderator workspace | Active administrators retain access; users and moderators receive maintenance responses |

Normal auth routes apply both public and authentication scopes. This ensures Public Website maintenance closes login and registration even when Authentication maintenance is off. Password recovery and OTP operations use those same normal auth scopes. The configured administrator sign-in route is outside the three Planora scopes; it still requires valid administrator credentials and an eligible account. Authenticated Admin workspace routes are subject to the Dashboard & Workspace scope, which they may bypass after server-side authentication and the active-account check.

`EnforceMaintenanceScope` runs after request authentication and the active-account check when those middleware apply. During Dashboard & Workspace maintenance, unauthenticated requests retain their normal authentication behavior; authenticated non-admin accounts receive a 503, while active Admins may continue into the protected workspace. Laravel's global `artisan down` middleware is separate from the three Planora modes. Its narrow exception list includes the configured administrator entry path and the admin maintenance/bootstrap routes needed for recovery; these exceptions do not grant authorization or bypass the route-level account checks.

The unauthenticated, read-only `GET /api/v1/maintenance/status` endpoint returns the three configured scope flags, visitor message, estimate, and retry interval. It does not expose the administrator entry path. Vue route guards use it for SPA navigation, since client-side route changes do not make a new request to `routes/web.php`. Backend middleware remains authoritative for page requests and APIs.

## Rotation and deployment

The entry path is a routing secret, not a credential or authorization mechanism. Continue to protect it with HTTPS in production, strong administrator passwords, account status checks, CSRF, login throttling, session regeneration, role/policy checks, and audit logs. The secret URL can still appear in browser history and web-server/proxy access logs; restrict log access and do not share it in tickets or public messages.

When rotating the value, update the secret manager and rebuild Laravel's cached configuration and route table as part of a coordinated deployment. Route caching captures the configured URI, so a changed value is not active until the route cache is rebuilt. Notify administrators through a private operational channel. The rotation does not change accounts or passwords; it invalidates the old entry URL.

The local `.env` value is ignored by Git. The repository intentionally does not define a default secret path in `.env.example`; each local or production environment must generate and configure its own value.

## Verification

Run focused checks from a configured development environment. PHPUnit feature tests require the isolated MySQL test database described in [testing database](TESTING_DATABASE.md).

```sh
php artisan test tests/Feature/Auth/AdministratorEntryTest.php
php artisan test tests/Feature/Admin/Mfa
php artisan test tests/Feature/Admin/SystemMaintenance/SystemMaintenanceManagementTest.php
node --test tests/js/system-maintenance.test.js
npm run build
```
