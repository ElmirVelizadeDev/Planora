# Authentication and security

This note describes the current authentication and authorization boundaries implemented by Planora. Server-side middleware, policies, Form Requests, and scoped services enforce access; Vue route guards are for navigation only.

## Session authentication

- The default Laravel `web` guard uses the session driver and Eloquent User provider. The first-party Vue SPA uses Sanctum stateful session authentication, credentials, CSRF cookies, and the shared Axios client.
- Login requires a valid account with an allowed role, active status, and verified email. Successful login regenerates the session; logout invalidates it.
- The current-user API uses `auth:sanctum` and active-account middleware. APIs that change data apply their own role, policy, request, and record-scope checks.
- My Profile mutations operate on the authenticated account without accepting target IDs. The profile request prohibits email, role, status, permissions, and password fields; avatar uploads are image/MIME/dimension/size validated. Password changes require the current password and configured password policy, rotate the remember token, revoke personal access tokens, and update the current session's password hash so other stateful sessions become invalid.
- Email remains read-only in My Profile until an authenticated email-change verification flow exists. Account deletion stays Admin-managed under the existing soft-delete and last-active-administrator protections.
- Vue route guards mirror the permission-enabled Moderator destinations, but Laravel independently enforces every role and permission check. A Moderator role does not grant module access by itself.

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
- Planora maintenance uses separate Public Website, Authentication, and Dashboard & Workspace scopes. Only an authenticated active Admin bypasses the Dashboard & Workspace scope; public and authentication scopes still apply to their matching routes. Laravel's native global maintenance gate is a separate operator-controlled mechanism. See [administrator access and maintenance](ADMIN_ACCESS_AND_MAINTENANCE.md).

## Registration and one-time codes

- Public registration cannot set a privileged role. Valid registration data is held in `pending_registrations` until email verification completes.
- OTP hashes are stored in the database; plaintext OTP codes are not persisted. Local development suppresses email delivery and displays instructions to issue a fresh code with the protected `php artisan auth:local-otp` command, which prints it only to the terminal. Outside local, the application uses the configured Laravel Mail transport.
- Registration and recovery codes expire after five minutes, have attempt limits, and are subject to resend cooldowns and route throttling. See [registration OTP lifecycle](REGISTRATION_OTP.md) and [local mail testing](LOCAL_MAIL_TESTING.md).
- `auth:local-otp` prints a fresh code only to the terminal and is restricted to local development or a MySQL test database whose name contains `test` or `testing`. Do not copy codes into logs or shared output.

## Password recovery and account controls

- Password recovery uses hashed OTPs, expiry and attempt/resend limits, a short-lived HttpOnly recovery cookie, and a single-use reset flow.
- Account roles are `user`, `moderator`, and `admin`; statuses are `active`, `inactive`, `suspended`, and `banned`. Inactive accounts are rejected by active-account middleware on protected APIs.
- User Management authorization applies policy checks, self-account restrictions, and protection against removing the last active administrator. User deletion is soft deletion; task deletion is also soft deletion.
- Project APIs allow active Admin management, permission-limited Moderator reads/status changes, or active User access to owned Projects. User Project lookup is scoped by `owner_id`; Project Task listing additionally requires Task ownership or assignment.
- Admin Category changes require an active Admin; Moderators with `categories.view` can access read-only Category views. The separate User Category catalog requires an active `user` role and returns active Categories only. Category detail usage counts and related records are scoped to Projects owned by the User and Tasks owned by or assigned to the User; they do not expose Admin global counts or other Users' private records.
- Project and Task category IDs are validated server-side. New assignments must use an active Category. An existing archived Category association can be retained during an update, but cannot be newly assigned. See [API documentation](API_DOCUMENTATION.md) and [database design](DATABASE_DESIGN.md).
- Avatar uploads are validated by the User Management request layer for allowed image formats, dimensions, and size.

## Notifications

The notification API is available only to authenticated active Users and Admins. Each list and read operation is scoped to the signed-in recipient; Moderators do not have notification access. Task notifications link to a Task only when the recipient's role and current task relationship permit that destination. Notifications do not carry administrator entry paths or maintenance secrets. See [API documentation](API_DOCUMENTATION.md) for endpoints and event types.

## Deployment

Keep `.env`, application keys, database/mail credentials, local OTP values, and recovery tokens out of version control and shared logs. Use HTTPS with secure session cookies in deployment. Configure and verify a production mail provider separately; local database OTP behavior is not a substitute for delivery testing. Configure Sanctum stateful domains, CORS, session domains, and CSRF for the deployed SPA origin.
