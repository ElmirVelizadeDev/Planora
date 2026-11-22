# Error handling and maintenance operations

## Error responses

Laravel renders the branded Blade error page in `resources/views/errors/page.blade.php` for HTTP errors. It is standalone HTML and does not depend on Vite or a database. Server errors are sanitized by the exception handler even when debug mode is enabled. API server errors return a safe JSON message and keep the original HTTP status. The Vue application uses dedicated error pages for 401, 403, 404, 419, 429, 500, and 503; its catch-all route renders the 404 page.

Unauthenticated access to protected web routes continues to use the existing login redirect, while API authentication failures return JSON. `/401` is a public SPA entry point for an explicit unauthorized page. Planora scope middleware returns JSON 503 responses for API/JSON requests and the branded HTML 503 page for browser requests.

## Maintenance commands and independent scopes

The Admin Profile menu links to **System Maintenance**. Its role-protected page configures three independent scopes: Public Website, Authentication, and Dashboard & Workspace. Scope state and shared visitor copy are stored in `storage/framework/planora-maintenance-scopes.json` under an exclusive file lock; no database table or Admin Settings value is used. Visitor copy can be saved separately from scope switches. Scope middleware is attached to the matching Laravel routes and API groups, so direct requests receive HTTP 503 while unselected areas remain available. Scoped API responses include `Retry-After` and a non-sensitive scope identifier.

Only authenticated, active Admins bypass the Dashboard & Workspace scope. Middleware ordering authenticates and checks account status before applying that scope. As a result, unauthenticated web requests retain the login redirect and unauthenticated API requests retain the normal JSON authentication response; authenticated non-admin accounts receive a 503. Admin dashboard and maintenance controls remain reachable. Admins do not bypass Public Website or Authentication scopes on those public/authentication routes. The CSRF-cookie endpoint, public branding endpoint, and logout operation are not put behind the Authentication scope.

Laravel's native `down` mechanism remains available as an emergency **global** maintenance gate. It blocks application requests except for a narrow list of admin bootstrap/maintenance paths and the configured administrator entry path; those paths still apply their own authentication and authorization checks. It uses Laravel's rendered 503 response and optional operator bypass secret. The Admin panel never creates or exposes that secret. Running `planora:maintenance-up` disables the native gate and clears all three scope flags; disabling all scopes through the Admin panel uses the same operational command. If the native gate is active, individual scope controls are disabled until global maintenance is turned off.

```powershell
php artisan planora:maintenance-status
php artisan planora:maintenance-down --message="Planora is receiving a scheduled update." --estimated-at="2026-11-16 18:00:00 Asia/Baku" --retry=60
php artisan planora:maintenance-up
```

`planora:maintenance-status` reports the global Laravel gate, each configured scope, and the overall state. `planora:maintenance-down` activates Laravel's global gate and accepts message, estimate, retry, and optional secret flags for the prerendered page. The Admin page configures scope state and shared visitor message/estimate without activating Laravel's global gate. Retry values are bounded to 15 seconds through 24 hours; messages are limited to 500 characters. Estimated times must parse and be in the future.

For a controlled operator bypass, pass Laravel's `--with-secret` or `--secret=...` to `planora:maintenance-down`. Laravel prints the bypass URL only in command output and sets its signed maintenance cookie when that URL is opened. Treat that URL as a credential: share it only through an approved operator channel, never put it in client code or API responses, and keep it out of public deployment logs. The Admin UI does not issue or expose this bypass secret. It remains an operational fallback for trusted deployment operators.

The standalone Blade 503 page displays only a navigation destination that is open for the current role and known scope state. Its Check again action probes the current page with a safe GET; it does not replay the request that produced the error. Automatic checks use the configured retry interval and stop after eight attempts. The Vue 503 page polls the public maintenance-status endpoint, offers only a role- and scope-accessible destination, and also stops automatic checks after eight attempts. API clients receive `Retry-After`; Planora scope responses identify the affected scope. Authentication scope applies to login, registration, OTP verification, and password recovery APIs; the Sanctum CSRF-cookie endpoint and logout remain available.

## Deployment guidance and limits

Laravel maintenance mode blocks the application at the front controller and returns HTTP 503. Its prerendered template is embedded in Laravel's maintenance file and can render before Composer and application services are booted. The file maintenance driver must be visible to every web node; multi-node deployments need a shared or coordinated maintenance store. A bypass cookie also depends on a consistent `APP_KEY` across nodes.

Maintenance mode reduces exposure to a partially deployed application but does not provide zero downtime and does not make incompatible database migrations safe. Recommended release sequence:

1. Build frontend assets and install backend dependencies in a new release directory before switching production traffic.
2. Enable maintenance mode with the branded pre-rendered page.
3. Apply backward-compatible expand/contract database migrations and any required cache refreshes.
4. Atomically switch the release pointer (or use the platform's equivalent atomic deployment mechanism), preserving shared storage and environment configuration.
5. Run health checks against the new release, then disable maintenance mode.
6. If health checks fail, switch back to the prior release while keeping schema changes backward-compatible; disable maintenance only after the previous release is healthy.

This repository does not define a production release/symlink or database migration pipeline. Operators must adapt these steps to the deployment platform, verify shared storage and cache behavior, and test rollback procedures. Avoid destructive schema changes in the same release as application code that still needs the old schema.
