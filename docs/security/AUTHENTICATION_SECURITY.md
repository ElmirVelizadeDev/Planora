# Authentication and security

This note describes the current authentication and authorization boundaries implemented by Planora. Server-side middleware, policies, Form Requests, and scoped services enforce access; Vue route guards are for navigation only.

## Session authentication

- The default Laravel `web` guard uses the session driver and Eloquent User provider. The first-party Vue SPA uses Sanctum stateful session authentication, credentials, CSRF cookies, and the shared Axios client.
- Login requires a valid account with an allowed role, active status, and verified email. Successful login regenerates the session; logout invalidates it.
- The current-user API uses `auth:sanctum` and active-account middleware. APIs that change data apply their own role, policy, request, and record-scope checks.
- My Profile mutations operate on the authenticated account without accepting target IDs. The profile request prohibits email, role, status, permissions, and password fields; avatar uploads are image/MIME/dimension/size validated. Password changes require the current password and configured password policy, rotate the remember token, revoke personal access tokens, and update the current session's password hash so other stateful sessions become invalid.
- Email remains read-only in My Profile until an authenticated email-change verification flow exists. Account deletion stays Admin-managed under the existing soft-delete and last-active-administrator protections.
- Vue route guards mirror the permission-enabled Moderator destinations, but Laravel independently enforces every role and permission check. A Moderator role does not grant module access by itself.

## Administrator multi-factor authentication and step-up

Administrator MFA is mandatory. After a valid admin password, an administrator with an existing factor receives a five-minute pre-authentication challenge; an administrator without one receives a temporary enrollment challenge and no authenticated workspace session. Enrollment starts from the sign-in page, displays only a no-store QR image, and requires a valid six-digit TOTP before enabling the factor and establishing a session. The seed is encrypted at rest and is not returned as a raw secret or setup URI. Ten recovery codes are created at activation; only their hashes are stored and each is single-use. Codes are shown once in page memory and should be stored securely. MFA cannot be disabled through the application.

TOTP and recovery-code failures are counted in the shared SQL database per account and challenge, serialized by an account row lock, and temporarily locked after configured limits. Named IP rate limits add another layer and must use shared cache storage in a multi-instance deployment. Expired and consumed challenges are rejected; a successful code resets the failure counters. Repeated failures are logged without the submitted values. Existing Admins without MFA must enroll through `/api/v1/auth/admin/mfa/enroll-login` and `/verify`; direct API calls cannot create an authenticated Admin session without completing MFA. Recovery-code regeneration remains available to enabled Admins after step-up. Moderators do not currently require MFA because the permission catalog limits them to scoped, non-security-sensitive reads and selected status changes; operators should revisit this if privileged Moderator capabilities are added.

Sensitive Admin mutations use purpose-bound, one-use step-up grants. An Admin verifies a TOTP or single-use recovery code through `/api/v1/admin/step-up/verify`; password-only step-up is not accepted. The grant is tied to the current session and purpose and expires after 60 seconds. The mutation middleware consumes it. The enforced purposes are maintained in `VerifyAdminStepUpRequest` and the route middleware declarations.

### Local administrator MFA recovery

If an Admin loses the Authenticator entry, first use one unused recovery code on the administrator sign-in challenge, if one is available. Recovery codes are single-use. To replace a lost factor when no usable code remains, an operator with access to the local development terminal can run `php artisan auth:mfa-reset <administrator-email>` from the Planora repository root. The command is restricted to the `local` application environment and an interactive terminal, checks that the exact account is an Admin, asks for confirmation, and requires the email to be re-entered. It does not change the account password, email, role, or status and has no HTTP endpoint.

The command clears only that Admin's MFA enrollment fields, recovery codes, and MFA attempt counters. It advances that account's authentication version, rotates its remember token, and revokes its Sanctum tokens so existing sessions and tokens cannot remain usable. Outstanding password-confirmed MFA challenges also become invalid. Mandatory MFA remains enabled as an application policy; the account must complete enrollment before receiving an authenticated session.

After reset, open the configured administrator sign-in page, enter the existing account email and password, and scan the newly displayed QR code in the Authenticator app. Enter the current six-digit code from the new entry before it expires to confirm enrollment. Planora encrypts the new seed at rest and returns ten recovery codes once after successful enrollment; store them securely offline. Do not copy QR images, provisioning data, TOTP codes, or recovery codes into logs, tickets, source control, or screenshots. Keep the Authenticator entry and recovery codes backed up securely. If both the authenticator and every recovery code are lost, repeat this local-only recovery procedure. Never run the command in production or relax the mandatory MFA checks.

## Moderator permissions

An Admin assigns permissions per Moderator from the fixed catalog in `config/user_management.php`. The catalog currently defines:

- `users.view`: view and search non-Admin accounts.
- `users.status.manage`: change status for accounts with the `user` role; requires `users.view`.
- `categories.view`: view active and archived Categories without changing them.
- `projects.view`: view Projects and related Task information without editing or deleting records.
- `projects.status.manage`: change Project status only; requires `projects.view`.
- `tasks.view`: view active Tasks and their details without editing, deleting, or restoring them.
- `tasks.status.manage`: change Task status only; requires `tasks.view`.
- `reports.view`: view Reports & Analytics; CSV export remains Admin-only.
- `activity.view`: review permitted User and authentication activity.

These assignments grant access only to their corresponding Admin pages and API operations. The dashboard requires at least one of the view permissions; its Users, Activity, Categories, Projects, Tasks, and Reports section flags follow the matching grants. Metric data is returned for the first five sections, while Reports uses its separate endpoints. Moderator status changes are limited to accounts with the `user` role and cannot target the signed-in account; Project and Task status mutations use their dedicated status permissions. Settings, role/permission assignment, and other Admin-only operations are not granted by Moderator status.

## Administrator access and maintenance

- The administrator sign-in page is registered as a Laravel web route from the server-side `PLANORA_ADMIN_ENTRY_PATH` setting. Production requires a URL-safe value of at least 48 characters. It is not a Vue route, Vite variable, or public API value.
- The route uses the `web` guard, session and CSRF middleware, and the existing login throttle. The server requires valid credentials, the actual `admin` role, active account status, and verified email; successful sign-in regenerates the session. A non-admin account is rejected.
- The entry response is non-cacheable and uses `Referrer-Policy: no-referrer`. Audit records redact the configured entry path. The path is a routing secret, not an authentication credential, and may still appear in browser or infrastructure access logs.
- Planora maintenance uses separate Public Website, Authentication, and Dashboard & Workspace scopes. Only an authenticated active Admin bypasses the Dashboard & Workspace scope; public and authentication scopes still apply to their matching routes, including MFA completion. If an Admin is locked out while Authentication maintenance is enabled, an operator must restore that scope through an existing Admin session or run `php artisan planora:maintenance-up` on the server before the Admin can finish MFA login/enrollment. Laravel's native global maintenance gate separately allows only the narrowly scoped Admin MFA challenge/enrollment endpoints needed to complete the password-confirmed flow. See [administrator access and maintenance](../operations/ADMIN_ACCESS_AND_MAINTENANCE.md).

## Registration and one-time codes

- Public registration cannot set a privileged role. Valid registration data is held in `pending_registrations` until email verification completes.
- OTP hashes are stored in the database; plaintext OTP codes are not persisted there. Non-local registration and recovery email are sent by the unique `DeliverAuthenticationOtp` queued job. Its OTP payload is encrypted, and the job sends only while the matching current OTP hash and expiry remain valid; stale resend jobs exit. Retry may redeliver the same valid code if the mail server accepted it before a worker failure, but does not create a second OTP. After permanent failure the pending OTP is expired or removed while the generic API response remains unchanged. Registration/resend/recovery endpoints also enforce a configurable minimum response time (`AUTH_ENUMERATION_MIN_RESPONSE_MS`, default 250ms) to reduce existence timing signals. Local development suppresses delivery and uses the protected `php artisan auth:local-otp` command.
- Registration and recovery codes expire after five minutes, have attempt limits, and are subject to resend cooldowns and route throttling. See [registration OTP lifecycle](REGISTRATION_OTP.md) and [local mail testing](../development/LOCAL_MAIL_TESTING.md).
- `auth:local-otp` prints a fresh code only to the terminal and is restricted to local development or a MySQL test database whose name contains `test` or `testing`. Do not copy codes into logs or shared output.

## Password recovery and account controls

- Password recovery uses hashed OTPs, expiry and attempt/resend limits, a short-lived HttpOnly recovery cookie, and a single-use reset flow. A recovery reset increments the authentication version, rotates the remember token, and revokes Sanctum tokens.
- Registration, recovery, account password changes, and Admin-managed password creation share a 12-character minimum, 128-character maximum, common-password rejection, and configured composition rules. Existing passwords remain valid for login. The optional-by-configuration Laravel uncompromised rule uses the Have I Been Pwned range protocol (only a SHA-1 prefix is sent); enable it with `PASSWORD_BREACH_CHECK_ENABLED=true` and confirm outbound access policy.
- Account roles are `user`, `moderator`, and `admin`; statuses are `active`, `inactive`, `suspended`, and `banned`. Inactive accounts are rejected by active-account middleware on protected APIs.
- User Management authorization applies policy checks, self-account restrictions, and protection against removing the last active administrator. Status changes, role/permission changes, soft deletion, restoration, and password changes increment the affected user's `auth_version`, rotate their remember token, and revoke their Sanctum tokens. Stateful sessions compare their version on protected requests, so restoring an account does not revive old cookies. User deletion is soft deletion; task deletion is also soft deletion.
- Project APIs allow active Admin management, permission-limited Moderator reads/status changes, or active User access to owned Projects. User Project lookup is scoped by `owner_id`; Project Task listing additionally requires Task ownership or assignment.
- Admin Category changes require an active Admin; Moderators with `categories.view` can access read-only Category views. The separate User Category catalog requires an active `user` role and returns active Categories only. Category detail usage counts and related records are scoped to Projects owned by the User and Tasks owned by or assigned to the User; they do not expose Admin global counts or other Users' private records.
- Project and Task category IDs are validated server-side. New assignments must use an active Category. An existing archived Category association can be retained during an update, but cannot be newly assigned. See [API documentation](../api/API_DOCUMENTATION.md) and [database design](../architecture/DATABASE_DESIGN.md).
- Avatar uploads are validated by the User Management request layer for allowed image formats, dimensions, and size.

## Notifications

The notification API is available only to authenticated active Users and Admins. Each list and read operation is scoped to the signed-in recipient; Moderators do not have notification access. Task notifications link to a Task only when the recipient's role and current task relationship permit that destination. Notifications do not carry administrator entry paths or maintenance secrets. See [API documentation](../api/API_DOCUMENTATION.md) for endpoints and event types.

## Global Search and backups

Global Search requires an authenticated active account. Laravel decides which result groups to query: Admins can search management records, Moderators receive only groups allowed by their individual view permissions and never receive Admin user records, and regular Users receive only their owned/assigned Tasks, owned Projects, and Categories visible to them. Search results contain minimal display data and internal Vue Router destinations; client-side filtering is not used as an authorization boundary.

Backup APIs and the `/admin/backups` page require an authenticated active Admin. Moderators do not receive access. Download, deletion, and restore use server-side backup identifiers; restore and deletion also require confirmation values tied to that identifier. Backup resources expose safe metadata rather than storage keys, checksums, encryption material, or filesystem paths. See [API documentation](../api/API_DOCUMENTATION.md) and [backup and disaster recovery](../operations/BACKUPS_AND_DISASTER_RECOVERY.md).

## Deployment

Keep `.env`, application keys, database/mail credentials, local OTP values, and recovery tokens out of version control and shared logs. Production requires `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, and secure HttpOnly Lax session cookies. Do not set `TRUSTED_PROXIES=*`; configure only the deployment operator's actual proxy IPs/CIDRs in `TRUSTED_PROXIES`, or leave it empty when no proxy is used. Configure explicit Sanctum stateful domains and frontend origins. The `.env.example` values for sync queue and file cache are local-only. Production must use a persistent queue (recommended Redis) and run `php artisan queue:work redis --tries=3`; use a shared Redis cache for rate limits/unique-job locks across instances (`CACHE_DRIVER=redis`). Multi-instance deployments also need shared session storage such as `SESSION_DRIVER=redis`, since the development default is file-based. Provision Redis and its credentials outside source control. The app intentionally fails startup in production when the queue is synchronous.
