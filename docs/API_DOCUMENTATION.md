# API documentation

The active JSON API is versioned under `/api/v1`. Laravel mounts `routes/api/v1.php` through `routes/api.php`; `php artisan route:list` is the authoritative route inventory. The first-party Vue SPA uses Sanctum stateful sessions and CSRF protection, not bearer tokens for browser login.

Protected routes require an authenticated active account and the role or policy described below. Vue route guards are not an API security boundary. The administrator sign-in entry is a configured Laravel web route, not a JSON API endpoint; see [administrator access and maintenance](ADMIN_ACCESS_AND_MAINTENANCE.md).

## Authentication

| Method | Endpoint | Access and purpose |
|---|---|---|
| `POST` | `/api/v1/auth/login` | Public, throttled session login |
| `POST` | `/api/v1/auth/register` | Public, start registration |
| `POST` | `/api/v1/auth/register/verify` | Public, verify registration OTP |
| `POST` | `/api/v1/auth/register/resend` | Public, resend registration OTP |
| `POST` | `/api/v1/auth/password/forgot` | Public, request recovery code |
| `POST` | `/api/v1/auth/password/resend` | Public, resend recovery code |
| `POST` | `/api/v1/auth/password/verify` | Public, verify recovery code and issue recovery cookie |
| `POST` | `/api/v1/auth/password/reset` | Public, reset password using verified recovery session |
| `POST` | `/api/v1/auth/logout` | Authenticated session logout |
| `GET` | `/api/v1/user` | Authenticated active account profile |
| `GET` | `/api/v1/user/overview` | Authenticated active account's own Project and Task counts; no additional role is required |
| `PATCH` | `/api/v1/user/profile` | Update the authenticated account's first name, last name, or phone; email and privileges are not editable here |
| `POST` | `/api/v1/user/avatar` | Upload or replace the authenticated account's profile photo |
| `DELETE` | `/api/v1/user/avatar` | Remove the authenticated account's profile photo |
| `PUT` | `/api/v1/user/password` | Change the authenticated account's password after current-password verification; throttled |

These account endpoints accept no user ID and operate only on the authenticated active account. Password changes revoke Sanctum personal access tokens, rotate the remember token, and invalidate other stateful sessions through the stored password hash. Email remains read-only because authenticated email changes do not currently have a verification workflow. Self-service account deletion is not available; account lifecycle remains Admin-managed.

See [authentication and security](AUTHENTICATION_SECURITY.md), [registration OTP lifecycle](REGISTRATION_OTP.md), and [local mail testing](LOCAL_MAIL_TESTING.md) for the authentication flow and development tooling.

`GET /api/v1/user/overview` has no query parameters. Its response is `data.projects` (`total`, `completed`) and `data.tasks` (`total`, `completed`, `pending`). Project counts include Projects owned by the signed-in account; Task counts include its owned or assigned Tasks.

## Public branding

| Method | Endpoint | Access and response |
|---|---|---|
| `GET` | `/api/v1/settings/branding` | Public, no parameters; returns `data.application_name`, `data.workspace_name`, and `data.application_logo_url` (nullable) |

## Admin APIs

Admin APIs require an authenticated active Admin or, for specifically delegated modules, an active Moderator with the corresponding permission. Create/update/delete operations that are Admin-only add an explicit `admin` role check. The Vue guard and sidebar mirror supported capabilities for navigation; Laravel remains authoritative.

### Moderator permissions

An Admin assigns each permission separately; the Moderator role alone grants no module access:

- `users.view` permits viewing and searching non-Admin accounts and reading role/permission definitions. `users.status.manage` permits changing status for accounts with the `user` role and requires `users.view`.
- `categories.view` permits read-only access to active and archived Categories.
- `projects.view` permits viewing Projects and related Task information. `projects.status.manage` permits changing Project status only and requires `projects.view`.
- `tasks.view` permits viewing active Tasks and details. `tasks.status.manage` permits changing Task status only and requires `tasks.view`.
- `reports.view` permits report overview, filter, and table reads; CSV export is Admin-only.
- `activity.view` permits authorized activity-log reads and exports. Moderator results are limited to User and Authentication events associated with non-Admin accounts.

The Admin dashboard is available to a Moderator with at least one of the six view permissions. Its response has permission-dependent `sections` flags for Users, Activity, Categories, Projects, Tasks, and Reports. Metric data is returned for permitted Users, Activity, Categories, Projects, and Tasks; the Reports flag indicates access to the separate report endpoints. Admins receive all sections. Permission assignment, Settings, full entity management, and report CSV export remain Admin-only except for the explicit status operations documented below.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/users` | User listing, filters, pagination, and statistics |
| `POST` | `/api/v1/admin/users` | Create a User (Admin only) |
| `GET` | `/api/v1/admin/users/{user}` | Authorized User details |
| `PATCH` | `/api/v1/admin/users/{user}` | Update a User (Admin only) |
| `POST` | `/api/v1/admin/users/{user}/password` | Change an account password (Admin only) |
| `DELETE` | `/api/v1/admin/users/{user}` | Soft-delete a User (Admin only) |
| `POST` | `/api/v1/admin/users/{userId}/restore` | Restore a deleted non-Admin User (Admin only) |
| `PATCH` | `/api/v1/admin/users/{user}/role` | Change role (Admin only) |
| `PATCH` | `/api/v1/admin/users/{user}/status` | Change status under User Management authorization |
| `PUT` | `/api/v1/admin/users/{user}/permissions` | Set moderator permissions (Admin only) |
| `GET` | `/api/v1/admin/users/activity` | Authorized activity listing |
| `GET` | `/api/v1/admin/users/roles-permissions` | Admin or Moderator with `users.view`; role and permission definitions |
| `GET` | `/api/v1/admin/roles-permissions` | Admin-only fixed role and permission catalog |
| `GET` | `/api/v1/admin/roles-permissions/moderators` | Admin-only searchable/paginated Moderator directory, role counts, and recent permission changes |
| `GET` | `/api/v1/admin/roles-permissions/moderators/{user}` | Admin-only Moderator permission details; non-Moderator targets are not found |
| `PUT` | `/api/v1/admin/roles-permissions/moderators/{user}` | Admin-only permission assignment, validated against the server catalog and audited |
| `GET` | `/api/v1/admin/dashboard` | Admin overview; Moderator section flags and metric data reflect each individually assigned view permission |
| `GET` | `/api/v1/admin/tasks` | Task listing, filters, pagination, and global statistics |
| `GET` | `/api/v1/admin/tasks/assignees` | Active normal-User assignment choices |
| `POST` | `/api/v1/admin/tasks` | Create a Task |
| `GET` | `/api/v1/admin/tasks/{task}` | Read Task details |
| `PATCH` | `/api/v1/admin/tasks/{task}` | Update Task data and assignment |
| `PATCH` | `/api/v1/admin/tasks/{task}/status` | Change only Task status; Admin or Moderator with `tasks.view` and `tasks.status.manage` |
| `DELETE` | `/api/v1/admin/tasks/{task}` | Soft-delete a Task |
| `POST` | `/api/v1/admin/tasks/{taskId}/restore` | Restore a soft-deleted Task (Admin only) |
| `GET` | `/api/v1/admin/projects` | Project listing, filters, pagination, and status statistics |
| `GET` | `/api/v1/admin/projects/owners` | Active normal-User Project owner choices |
| `POST` | `/api/v1/admin/projects` | Create a Project |
| `GET` | `/api/v1/admin/projects/{project}` | Read Project details |
| `PATCH` | `/api/v1/admin/projects/{project}` | Update Project data, owner, status, dates, or Category |
| `PATCH` | `/api/v1/admin/projects/{project}/status` | Change only Project status; Admin or Moderator with `projects.view` and `projects.status.manage` |
| `DELETE` | `/api/v1/admin/projects/{project}` | Delete a Project; linked Tasks are detached |
| `GET` | `/api/v1/admin/projects/{project}/tasks` | List and filter Project Tasks |
| `POST` | `/api/v1/admin/projects/{project}/tasks` | Create a Task linked to a Project |
| `GET` | `/api/v1/admin/categories` | List active, archived, or all Categories with global statistics |
| `POST` | `/api/v1/admin/categories` | Create a Category |
| `GET` | `/api/v1/admin/categories/{category}` | Read Category details and global related Projects/Tasks |
| `PATCH` | `/api/v1/admin/categories/{category}` | Update a Category |
| `DELETE` | `/api/v1/admin/categories/{category}` | Archive a Category (soft delete) |
| `POST` | `/api/v1/admin/categories/{category}/restore` | Restore an archived Category |
| `GET` | `/api/v1/admin/reports` | Read database-backed report metrics, trends, and breakdowns using optional report filters |
| `GET` | `/api/v1/admin/reports/filters` | Search Users, Projects, or Categories for report filters |
| `GET` | `/api/v1/admin/reports/tables/{table}` | Search, sort, and paginate a filtered report detail table |
| `GET` | `/api/v1/admin/reports/export` | Export the filtered report as CSV |

The User listing accepts `search`, `role`, `status`, `verification` (`verified` or `unverified`), `registered_from`, `registered_to`, `sort` (`newest`, `oldest`, `name_asc`, or `name_desc`), `page`, and `per_page` (1–10). Admins can set `deleted=1` to list soft-deleted accounts; Moderators cannot access that view. Restoring deleted accounts is Admin-authorized.

For `PATCH /api/v1/admin/users/{user}/status`, Admins may update another account's status. A Moderator needs both `users.view` and `users.status.manage`; the policy forbids changing the signed-in account's status and restricts Moderator targets to accounts with the `user` role. The JSON body requires `status` in `active`, `inactive`, `suspended`, or `banned`; `reason` is required for `suspended` or `banned` and must be a string of 5–2000 characters. For other statuses, `reason` is optional and may be null; if supplied, it has the same string-length bounds.

Admins and Moderators with `reports.view` can call the report overview, filter-options, and table endpoints. Only Admins can use CSV export. Overview, table, and CSV export requests accept these global filters:

- `from` and `to`: optional dates in `Y-m-d` format, no later than today in the configured application timezone. The range is inclusive, `from` must not follow `to`, and it cannot exceed 366 calendar days. If omitted, `to` defaults to today and `from` defaults to 29 days before `to` (30 calendar days).
- `user_id`: optional integer identifying an existing, non-deleted user.
- `project_id`: optional integer identifying an existing project.
- `task_status`: optional value `pending`, `in_progress`, or `completed`.
- `category_id`: optional integer identifying an existing category.

The CSV export applies these filters when building its report and detail rows. The filter-options endpoint requires `type` (`users`, `projects`, or `categories`) and accepts an optional `search` string up to 120 characters. Date filters affect new-record counts, trends, recent-record tables, task-assignment distribution, and category usage. User and Project filters scope their respective records and related Tasks. Task status affects task metrics, task tables, assignment distribution, and Task-category usage. Category filters scope Project/Task metrics and category usage. Active/archive catalog counts and current status distributions are lifetime/current-scope metrics, not period metrics; overdue and upcoming deadline queues are based on current due dates. Project records have no soft-delete archive state; Tasks and Users with soft-deleted rows are excluded from their normal totals, while archived Categories retain historical relationship counts. The previous-period growth percentage compares new records with the immediately preceding period of equal length and is unavailable when the previous count is zero. Completion rates are completed records divided by all current records in the filtered scope and are unavailable for a zero-record scope.

The status-only Admin Project and Task endpoints require a JSON `status` field, which is the only validated and updated field. Project status must be one of `planned`, `active`, `on_hold`, or `completed`; Task status must be `pending`, `in_progress`, or `completed`. Admins may use either endpoint. A Moderator needs the matching view and status permissions (`projects.view` plus `projects.status.manage`, or `tasks.view` plus `tasks.status.manage`). Each response contains the updated resource under `project` or `task`, respectively. The general Project and Task `PATCH` routes remain Admin-only.

### Admin Settings

Every Settings endpoint requires an authenticated active Admin; Moderators are not authorized. The group path is restricted to `general`, `security`, or `email`. These routes define no query parameters.

| Method | Endpoint | Request and response |
|---|---|---|
| `GET` | `/api/v1/admin/settings` | No body. Returns `data.groups` with `configured` and `updated_at` per group, plus overall `updated_at` and `updated_by`. |
| `GET` | `/api/v1/admin/settings/{group}` | No body. Returns the group's current values under `data`; the general group exposes `application_logo_url` instead of the stored path, and the email group omits `mail_password` and includes `mail_password_configured`. |
| `PUT` | `/api/v1/admin/settings/{group}` | JSON body with the group's settings listed below. Returns `message` and the updated group under `data`. |
| `POST` | `/api/v1/admin/settings/{group}/reset` | No settings fields required. Restores that group to its defaults and returns `message` and the group under `data`. |
| `POST` | `/api/v1/admin/settings/email/test` | JSON body: required `recipient`, RFC-valid email, maximum 255 characters. Rate-limited to three requests per minute. Returns a message if the mail transport accepts the test; returns HTTP 422 with a message if sending fails. |
| `POST` | `/api/v1/admin/settings/general/logo` | Multipart form with required `logo` file: PNG, JPG, JPEG, or WEBP; maximum 2048 KB; width and height each 32–3000 pixels. Returns `message` and `data.application_logo_url`. |
| `DELETE` | `/api/v1/admin/settings/general/logo` | No request fields. Removes the logo and returns `message` with `data.application_logo_url: null`. |

The `PUT` body uses top-level fields. Required fields must be supplied; nullable or `sometimes` fields may be omitted:

- **General:** `application_name` and `workspace_name` are required strings up to 100 characters; `workspace_description` is nullable text up to 500 characters; `contact_email` is nullable RFC-valid email up to 255 characters; `default_language` is `en`; `timezone` must be a valid timezone; `date_format` is one of `Y-m-d`, `d/m/Y`, `m/d/Y`, or `d M Y`; `time_format` is `24h` or `12h`. `application_logo` is prohibited in this body and must use the logo endpoint.
- **Security:** `minimum_password_length` is an integer from 8–64; `require_uppercase`, `require_lowercase`, `require_number`, and `require_special_character` are required booleans; `max_login_attempts` is an integer from 3–20; `login_lockout_minutes` is 1–60; `otp_expiration_minutes` is 2–15; `otp_max_attempts` is 3–10; and `otp_resend_cooldown_seconds` is 30–600.
- **Email:** `mail_mailer` is one of `smtp`, `sendmail`, `log`, or `array`; `mail_host` is a nullable hostname string up to 255 characters; `mail_port` is an integer from 1–65535; `mail_encryption` is `none`, `ssl`, or `tls`; `mail_username` is nullable text up to 255 characters; `mail_from_address` is a required RFC-valid email up to 255 characters; `mail_from_name` is a required string up to 100 characters. `mail_password` is optional, nullable text up to 1000 characters; `remove_mail_password` is an optional boolean. The password is never returned by the API; only whether one is configured is exposed.

## Notifications

These endpoints require an authenticated active account with the `user` or `admin` role. Moderators are not authorized. Every list, read, and unread count is restricted to the authenticated recipient; trying to read another recipient's notification returns 404.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/notifications` | List the recipient's notification history |
| `PUT` | `/api/v1/notifications/{notification}/read` | Mark one owned notification as read |
| `POST` | `/api/v1/notifications/read-all` | Mark all of the recipient's unread notifications as read |

The list accepts `filter` (`all` or `unread`), `page`, and `per_page` (1–50; default 15). It returns notification `data` and pagination metadata, including `unread_count`. Notification resources contain the ID, event type, title, description, read/created timestamps, and a role-checked internal destination when the related Task is still available to the recipient. Event types include task assignment, significant task updates, status changes, completion, reopening, and due-soon reminders; Admin events include verified user registration, account status/role/permission changes, account deletion/restoration, other Admin-created tasks, and maintenance changes. Admin events go to other active Admins, while task and account notifications are scoped to their intended active User recipients. Task reminders are scheduled daily for eligible tasks due the next day. Notification persistence is described in [database design](DATABASE_DESIGN.md).

## Maintenance APIs

The public status endpoint does not require authentication. The control endpoints require an authenticated active Admin; the Admin workspace remains available during the Planora Dashboard & Workspace scope.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/maintenance/status` | Read the configured scope flags and public maintenance message, estimate, and retry interval |
| `GET` | `/api/v1/admin/system-maintenance` | Read overall, native, configured-scope, and effective-scope status |
| `PATCH` | `/api/v1/admin/system-maintenance/preferences` | Update the visitor message, estimate, and retry interval |
| `PATCH` | `/api/v1/admin/system-maintenance/scopes/{scope}` | Enable or disable one scope (`public-website`, `authentication`, or `dashboard-workspace`) |
| `POST` | `/api/v1/admin/system-maintenance/enable` | Enable all three Planora scopes |
| `POST` | `/api/v1/admin/system-maintenance/disable` | Disable all Planora scopes and Laravel global maintenance |

The public status response contains only scope state and visitor-facing copy; it does not expose the administrator entry path. Scope-protected API requests return HTTP 503 with `Retry-After`, a public message, and the affected scope. The Admin panel changes Planora's three independent scopes, not Laravel's global `down` gate. The enable/disable control endpoints are rate-limited; if native global maintenance is active, scope changes return a conflict until the global gate is disabled.

## Activity Logs and Audit Trail

Activity Logs are immutable through the application API. All endpoints require an authenticated, active Admin or a Moderator with the `activity.view` permission. Admins can view all events; Moderators see only Users and Authentication events associated with non-Admin accounts. Project and Task create/update/status/assignment/delete/restore operations use the shared logger inside their mutation transactions. Permission changes, settings changes, category changes, authentication, and report/log exports are also recorded. The existing `/api/v1/admin/users/activity` endpoint remains available with its original response contract.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/activity-logs` | Search, filter, sort, paginate, and review audit events with matching statistics |
| `GET` | `/api/v1/admin/activity-logs/filters` | Load available modules, actions, statuses, and searchable actors within the viewer's scope |
| `GET` | `/api/v1/admin/activity-logs/statistics` | Read aggregate counts for the supplied log filters |
| `GET` | `/api/v1/admin/activity-logs/{activityLog}` | Read one authorized event and its safe change/request details |
| `GET` | `/api/v1/admin/activity-logs/export` | Stream all matching events as UTF-8 CSV; export is audited |

List/export filters include `search`, `actor_id`, `module`, `subject_type`, `event`, `status`, `from`, and `to`; dates are inclusive and may span at most 366 days. List pagination accepts `per_page` values of 10, 25, 50, or 100. Sortable fields are `created_at`, `event`, `module`, `status`, and `actor`. Audit context and change values are recursively filtered by sensitive field name, including password, OTP/code, token, session, cookie, credential, authorization, CSRF, and API/private-key fields. Request bodies and header collections are not stored; user agent and a UUID request ID set by trusted server middleware are retained. Historical actor and subject snapshots let records remain readable after related accounts are removed. CSV cells are escaped and formula-prefixed values are neutralized.

The table key is one of `registrations`, `projects`, `tasks`, `overdue`, `upcoming`, `categories`, `unused_categories`, or `assignees`; table requests support `search`, `sort`, `direction`, `page`, and `per_page` (10, 25, or 50). The overview includes the first page of each detail table; subsequent pages use the table endpoint so full database tables are not sent to the browser. CSV export streams all rows matching the global report filters, includes detail rows and headers, uses UTF-8 with a BOM, and does not include email addresses, credentials, or authentication data. Overdue means an open Task whose due date is before today; completed Tasks are excluded. Completion-history trends and on-time/late completion cannot be calculated because the schema does not store status-change timestamps.

Custom report date inputs and server-side date-range calculations use the configured application timezone. It defaults to UTC and can be changed through Admin Settings > General. The Vue preset controls currently generate date ranges from UTC calendar dates; with a non-UTC application timezone, a preset's dates may not align with the server's configured-zone `today`. The API returns report period boundaries and trend dates as `Y-m-d` date-only values, not UTC timestamps. In the Vue interface, trend labels and date-only table cells format those date-only values in UTC to prevent browser-local date shifts; date-time table cells and the `generated_at` display use the browser's default timezone. These display rules do not change the timezone used for server-side report calculations. The upcoming-deadline table covers open Tasks due from today through 13 days ahead (14 calendar dates, inclusive).

Admin Category listing accepts `search`, `status` (`active`, `archived`, or `all`), `sort` (`name`, `created_at`, `updated_at`), `direction`, `page`, and `per_page` (1–100; default 10). Its statistics cover global total, active, archived, and current-month Category counts. Category names are unique across active and archived records. The API does not expose permanent Category deletion; the database foreign keys restrict deleting a referenced Category.

## User Category catalog

These read-only endpoints require an authenticated active account with the `user` role. They do not grant access to Admin Category management.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/categories` | List active Categories with accessible usage counts |
| `GET` | `/api/v1/categories/{category}` | Read an active Category and accessible related records |

The catalog supports `search`, `sort` (`name` or `created_at`), `direction`, `page`, and `per_page` (1–50; default 12). Archived Categories are excluded from both listing and direct details. Category detail counts and returned records are User-scoped: Projects must be owned by the current User; Tasks must be owned by or assigned to the current User. Counts therefore need not equal Admin global counts.

## User Project and Task APIs

These endpoints require an authenticated active normal User. User Project collections and details are restricted to Projects owned by the current User. Task access is restricted to Tasks owned by or assigned to the current User.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/projects` | List owned Projects; supports search, status, Category, and pagination filters |
| `GET` | `/api/v1/projects/{project}` | Read an owned Project |
| `GET` | `/api/v1/projects/{project}/tasks` | List accessible Tasks in an owned Project; supports status, priority, Category, and pagination filters |
| `GET` | `/api/v1/tasks` | List owned/assigned Tasks with search, status, priority, due-state, Category, sort, date-range, and pagination filters |
| `POST` | `/api/v1/tasks` | Create a Task owned by the current User |
| `GET` | `/api/v1/tasks/{task}` | Read an owned or assigned Task |
| `PATCH` | `/api/v1/tasks/{task}` | Update an owned Task |
| `PATCH` | `/api/v1/tasks/{task}/status` | Update status of an owned or assigned Task |
| `DELETE` | `/api/v1/tasks/{task}` | Soft-delete an owned Task |

Project and Task creation/update accept an optional nullable `category_id`. New assignments must reference an active Category. An existing archived Category may remain selected when an existing Project or Task is updated without changing that association; it cannot be newly assigned. Project and Task resources return a nested Category summary (`id`, `name`, `color`, and `status`) when loaded, including `status: archived` for retained historical associations.

Project lifecycle values are `planned`, `active`, `on_hold`, and `completed`. Task statuses and priorities are defined by `app/Enums/TaskStatus.php` and `app/Enums/TaskPriority.php`. Collection responses use `data` and pagination `meta`; validation errors use Laravel's JSON validation response. Request parameter names and bounds are validated by Form Requests under `app/Http/Requests/Api/V1/`.

For table relationships and deletion behavior, see [database design](DATABASE_DESIGN.md). For access boundaries, see [authentication and security](AUTHENTICATION_SECURITY.md).
