# System architecture

## Request flow

Planora serves a Vue single-page application from Laravel's Blade shell. Vue Router loads public, authentication, User, Admin, and error pages; the shared Axios client calls Laravel's `/api/v1` routes. Sanctum authenticates first-party SPA requests with the Laravel session and CSRF protection. Controllers and Form Requests validate and authorize input, services apply domain queries and operations, and Eloquent persists data.

```mermaid
flowchart LR
    Browser --> Blade[Blade SPA shell]
    Blade --> Vue[Vue, Router, Pinia]
    Vue --> Axios[Shared Axios client]
    Axios --> Routes[/api/v1 routes]
    Routes --> Middleware[Sanctum, active account, role]
    Middleware --> Requests[Form Requests and controllers]
    Requests --> Services[Application services]
    Services --> Eloquent[Eloquent models]
    Eloquent --> MySQL[(Configured database)]
```

## Application boundaries

- `routes/web.php` serves the frontend shell and web routes. `routes/api.php` mounts `routes/api/v1.php` under `/api/v1`.
- Auth, User, and Admin Vue pages use separate layouts. The route guard provides navigation behavior; Laravel independently protects API data and mutations.
- User Tasks are scoped to the authenticated User as owner or assignee. User Projects are scoped to the current User's Project owner assignment; Project Task queries additionally apply the Task owner/assignee rule.
- Categories are a shared catalog. Admin endpoints provide full management, Moderators with `categories.view` can access read-only Admin views, and User endpoints expose active categories only. Optional nullable Category relationships belong to Projects and Tasks. Archive uses soft deletion and does not remove associations.
- Admin Category usage data is global. User Category counts and detail lists are scoped to Projects owned by the User and Tasks owned by or assigned to the User, so category details do not disclose inaccessible records.
- New Project and Task category assignments must reference an active Category. Existing archived associations can remain unchanged on update and are serialized with archived status for historical display.
- Admin-only operations include Category changes, full Project and Task management, Reports CSV export, Settings, and Moderator permission assignment. Active Moderators can read permitted User, Activity, Category, Project, Task, and Reports areas and can make only explicitly granted status changes. Their fixed permissions are assigned per account; a Moderator role alone grants no module access. Laravel policies and middleware remain authoritative.
- A Task may be standalone or linked to a Project. The nullable foreign key preserves the Task when its Project is deleted by setting `project_id` to null.
- Activity Logs reuse the existing `user_activity_logs` table and centralized `UserActivityLogger`; domain services write audit rows inside their mutation transactions. Credentials and request secrets are redacted. Admins can review all audit events, while Moderators require `activity.view` and are restricted to user/account activity for non-Admin accounts.
- Admin dashboard aggregates are served by one permission-aware endpoint. Its `sections` flags reflect each viewer's Users, Activity, Categories, Projects, Tasks, and Reports permissions. Admins receive all sections; Moderators receive only granted sections. Metric data is returned for permitted Users, Activity, Categories, Projects, and Tasks; the Reports flag identifies access to the separate report endpoints.
- Task soft deletion is recoverable through an Admin-only restore endpoint. Project deletion remains permanent; its task foreign key is null-on-delete so historical Tasks survive independently.
- Moderator permissions are a fixed server-side catalog in `config/user_management.php`, assigned per Moderator in `users.moderator_permissions`. The catalog includes view permissions for Users, Categories, Projects, Tasks, Reports, and Activity, plus status-only permissions for Users, Projects, and Tasks; each status permission requires its corresponding view permission. Role creation and arbitrary permission creation are not supported. See [authentication and security](AUTHENTICATION_SECURITY.md) for the exact permission meanings.

## Administrator access, maintenance, and notifications

- The administrator entry URL is read from `PLANORA_ADMIN_ENTRY_PATH` and registered only in `routes/web.php`. It serves a marker in the Blade SPA shell; the Vue sign-in form posts to the current URL. The entry path is not a Vue route or public API value. Authentication still uses the web session, CSRF, login throttling, and server-side role/account checks.
- Planora maintenance scopes are `public_website`, `authentication`, and `dashboard_workspace`. `EnforceMaintenanceScope` protects the relevant web and API routes. State and shared visitor copy live in a lock-protected JSON file under `storage/framework`; Laravel global maintenance remains a separate mode.
- Authenticated active Admins bypass the Dashboard & Workspace scope only. Middleware priority ensures authentication and active-account checks occur before the workspace-scope exception. Public/authentication scopes and Laravel's global maintenance gate remain independently enforced.
- `UserNotification` records are persisted in `user_notifications`. `NotificationService` creates recipient-scoped task assignment, significant update, status/completion/reopen, and due-soon notifications, plus Admin notices for verified registrations, account management, Admin-created tasks, and maintenance changes. User/Admin APIs scope records and read actions to the authenticated recipient; Moderators are not authorized for these notification endpoints.
- `planora:notify-tasks-due-soon` is scheduled daily at 08:00 UTC with overlap prevention. It rechecks task due date, completion state, and current active assignee before creating an idempotent reminder. Production hosts must run `php artisan schedule:run` every minute; a scheduled command definition alone does not execute reminders.

## Runtime and tooling

The backend uses Laravel 10 and PHP. The frontend uses Vue 3, Vue Router, Pinia, Axios, and Vite. MySQL is selected by `.env.example` and can be configured through the environment. CSS loads through one `resources/css/app.css` entry: tokens, base reset/typography, then route and component styles through Vue SFC imports. See [database design](DATABASE_DESIGN.md), [API documentation](API_DOCUMENTATION.md), and the root [Planora architecture](../PLANORA_ARCHITECTURE.md) for related details.
