# Error handling and maintenance operations

## Error responses

Laravel renders the shared branded templates in `resources/views/errors` for HTTP errors. They are standalone Blade HTML and do not depend on Vite, JavaScript, databases, or third-party asset hosts. Server errors are sanitized by the exception handler even when debug mode is enabled. API server errors return a safe JSON message and keep the original HTTP status. The Vue application uses one shared error page for its 401, 403, 404, 419, 429, 500, and 503 routes; its catch-all route renders the 404 page.

Unauthenticated access to protected routes continues to use the existing login redirect. `/401` is a public SPA entry point for an explicit unauthorized page. The maintenance middleware returns JSON for API/JSON clients and the branded HTML 503 page for browser navigation.

## Maintenance commands

Maintenance mode is deliberately operational and file-based, not an Admin Settings database value. The Admin Profile three-dot menu links to **System Maintenance**, which provides a role-protected status page and invokes the existing Artisan commands through authenticated, CSRF-protected API operations. The control page and its API endpoints are narrowly allowlisted by the native maintenance middleware. All other workspace pages and application APIs remain blocked. The current-user endpoint is allowlisted only so that its server-side middleware can return account bootstrap data to active Admins; it returns 503 for other roles while maintenance is on. This lets an administrator disable maintenance even when the database-backed Admin Settings area is unavailable.

```powershell
php artisan planora:maintenance-status
php artisan planora:maintenance-down --message="Planora is receiving a scheduled update." --estimated-at="2026-11-14 18:00:00 Asia/Baku" --retry=60
php artisan planora:maintenance-up
```

The public message is configurable through `PLANORA_MAINTENANCE_MESSAGE`, optional time through `PLANORA_MAINTENANCE_ESTIMATED_AT`, and default retry interval through `PLANORA_MAINTENANCE_RETRY_AFTER`. CLI flags override these values for the pre-rendered page. Retry values are bounded to 15 seconds through 24 hours; messages are limited to 500 characters. Estimated times must parse and be in the future.

For a controlled operator bypass, pass Laravel's `--with-secret` or `--secret=...` to `planora:maintenance-down`. Laravel prints the bypass URL only in command output and sets its signed maintenance cookie when that URL is opened. Treat that URL as a credential: share it only through an approved operator channel, never put it in client code or API responses, and keep it out of public deployment logs. The Admin UI does not issue or expose this bypass secret. It remains an operational fallback for trusted deployment operators.

The 503 page retries availability checks periodically and also has a manual retry action. API clients receive `Retry-After` and can retry according to their own backoff policy.

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
