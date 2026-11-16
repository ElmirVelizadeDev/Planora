# Administrator access and maintenance

## Administrator entry path

`PLANORA_ADMIN_ENTRY_PATH` configures the server-side administrator sign-in URL. It is read through `config/planora.php` and registered only as Laravel web routes in `routes/web.php`. The value must be URL-safe and at least 48 characters. The application does not place it in Vue routes, Vite variables, public HTML, or API response data.

Generate a value with a cryptographically secure random generator, for example:

```sh
php -r "echo 'entry-'.bin2hex(random_bytes(32)), PHP_EOL;"
```

Set the generated value as `PLANORA_ADMIN_ENTRY_PATH` in the ignored local `.env` file or in the production secret manager. Do not commit the value or add a `VITE_` prefix. The local development configuration must also use the intended `APP_URL` and web server host.

The GET route serves the normal Vite shell with a server-rendered `data-planora-view="admin-auth"` marker. `App.vue` renders the dedicated administrator sign-in screen only for that response. The frontend posts to its current URL, so the secret path is not embedded in the JavaScript bundle. The POST route uses the `web` middleware stack, including session and CSRF middleware, and the existing `auth-login` rate limiter.

Successful administrator sign-in uses the existing `web` guard and `User` record. Credentials, actual database role, active status, and verified email are checked on the server. The controller regenerates the session after authentication, returns the existing `UserResource`, and redirects to `/admin/dashboard`. A valid non-admin account is rejected. Login audit records replace the secret request path with `[protected-admin-entry]`.

## Maintenance modes

Planora stores three independent flags in `MaintenanceScopeStore`:

| Scope | Normal routes affected | Administrator behavior |
| --- | --- | --- |
| `public_website` | Home, legal pages, regular auth pages and regular auth API operations | Secret entry remains available; administration remains available |
| `authentication` | Regular login, registration, OTP verification/resend, and password recovery | Secret entry remains available; administration remains available |
| `dashboard_workspace` | Authenticated user and moderator workspace | Active administrators retain access; users and moderators receive maintenance responses |

Normal auth routes apply both public and authentication scopes. This ensures Public Maintenance closes login and registration even when Auth Maintenance is off. Password recovery and OTP operations use those same normal auth scopes. The dedicated administrator routes have no Planora maintenance scope; their own administrator credential and account checks are mandatory.

`EnforceMaintenanceScope` runs after request authentication and the active-account check, so it can distinguish an active admin session from a guest or regular account. Laravel's global `artisan down` maintenance middleware is separate from the three Planora modes. The custom `PreventRequestsDuringMaintenance` dynamically exempts only the configured administrator entry path from the framework-wide gate; it does not grant access or bypass authentication.

The read-only `GET /api/v1/maintenance/status` endpoint returns only the three scope flags and public maintenance copy. Vue route guards use it for SPA navigation, since client-side route changes do not make a new request to `routes/web.php`. Backend middleware remains authoritative for page requests and APIs.

## Rotation and deployment

The entry path is a routing secret, not a credential or authorization mechanism. Continue to protect it with HTTPS in production, strong administrator passwords, account status checks, CSRF, login throttling, session regeneration, role/policy checks, and audit logs. The secret URL can still appear in browser history and web-server/proxy access logs; restrict log access and do not share it in tickets or public messages.

When rotating the value, update the secret manager and rebuild Laravel's cached configuration and route table as part of a coordinated deployment. Route caching captures the configured URI, so a changed value is not active until the route cache is rebuilt. Notify administrators through a private operational channel. The rotation does not change accounts or passwords; it invalidates the old entry URL.

The local `.env` value is ignored by Git. The repository intentionally does not define a default secret path in `.env.example`; each local or production environment must generate and configure its own value.

## Verification

Focused checks:

```sh
php artisan test tests/Feature/Auth/AdministratorEntryTest.php
php artisan test tests/Feature/Admin/SystemMaintenance/SystemMaintenanceManagementTest.php
node --test tests/js/system-maintenance.test.js
npm run build
```
http://planora.test/entry-455f49967429b2b956a0da1bdbc814f3e35ba88bd93dd815e63e0ce222e1d2a3