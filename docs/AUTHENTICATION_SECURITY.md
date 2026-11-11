# Authentication and Security

This note describes the current authentication and authorization boundaries implemented by Planora. Server-side middleware, policies, Form Requests, and scoped services enforce access; Vue route guards are for navigation only.

## Session authentication

- The default Laravel `web` guard uses the session driver and Eloquent User provider. The first-party Vue SPA uses Sanctum stateful session authentication, credentials, CSRF cookies, and the shared Axios client.
- Login requires a valid account with an allowed role, active status, and verified email. Successful login regenerates the session; logout invalidates it.
- The current-user API uses `auth:sanctum` and active-account middleware. APIs that change data apply their own role, policy, request, and record-scope checks.
- Admin Vue routes require the `admin` role; User routes require `user`. Moderator permissions exist for selected User Management APIs, but the Admin Vue layout does not admit moderators.

## Registration and one-time codes

- Public registration cannot set a privileged role. Valid registration data is held in `pending_registrations` until email verification completes.
- OTP hashes are stored in the database. In `APP_ENV=local`, the registration OTP can also be read from the local verification table; outside local, the application uses the configured Laravel Mail transport.
- Registration and recovery codes expire after five minutes, have attempt limits, and are subject to resend cooldowns and route throttling. See [Registration OTP lifecycle](REGISTRATION_OTP.md) and [Local mail testing](LOCAL_MAIL_TESTING.md).
- `auth:local-otp` prints a fresh code only to the terminal and is restricted to local development or a MySQL test database whose name contains `test` or `testing`. Do not copy codes into logs or shared output.

## Password recovery and account controls

- Password recovery uses hashed OTPs, expiry and attempt/resend limits, a short-lived HttpOnly recovery cookie, and a single-use reset flow.
- Account roles are `user`, `moderator`, and `admin`; statuses are `active`, `inactive`, `suspended`, and `banned`. Inactive accounts are rejected by active-account middleware on protected APIs.
- User Management authorization applies policy checks, self-account restrictions, and protection against removing the last active administrator. User deletion is soft deletion; task deletion is also soft deletion.
- Project APIs require an active Admin for management or an active User for assigned-project access. User Project lookup is scoped by `owner_id`; Project Task listing additionally requires Task ownership or assignment.
- Avatar uploads are validated by the User Management request layer for allowed image formats, dimensions, and size.

## Deployment

Keep `.env`, application keys, database/mail credentials, local OTP values, and recovery tokens out of version control and shared logs. Use HTTPS with secure session cookies in deployment. Configure and verify a production mail provider separately; local database OTP behavior is not a substitute for delivery testing. Configure Sanctum stateful domains, CORS, session domains, and CSRF for the deployed SPA origin.
