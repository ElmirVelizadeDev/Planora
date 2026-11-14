# System Architecture

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
- Categories are a shared catalog. Admin endpoints manage categories; the User endpoints expose active categories only. Optional nullable Category relationships belong to Projects and Tasks. Archive uses soft deletion and does not remove associations.
- Admin Category usage data is global. User Category counts and detail lists are scoped to Projects owned by the User and Tasks owned by or assigned to the User, so category details do not disclose inaccessible records.
- New Project and Task category assignments must reference an active Category. Existing archived associations can remain unchanged on update and are serialized with archived status for historical display.
- Admin Project, Task, Category, Reports, Settings, and Roles & Permissions APIs are restricted to active Admin sessions. Moderators can access only individually granted User and Activity capabilities; the SPA sidebar and route guard reflect those same capabilities while Laravel remains authoritative.
- A Task may be standalone or linked to a Project. The nullable foreign key preserves the Task when its Project is deleted by setting `project_id` to null.
- Activity Logs reuse the existing `user_activity_logs` table and centralized `UserActivityLogger`; domain services write audit rows inside their mutation transactions. Credentials and request secrets are redacted. Admins can review all audit events, while Moderators require `activity.view` and are restricted to user/account activity for non-Admin accounts.
- Admin dashboard aggregates are served by one role-aware endpoint. Admins receive account, project, task, overdue, and recent activity summaries; Moderators receive only Users and/or Activity sections their permissions allow.
- Task soft deletion is recoverable through an Admin-only restore endpoint. Project deletion remains permanent; its task foreign key is null-on-delete so historical Tasks survive independently.
- Moderator permissions are a fixed server-side catalog in `config/user_management.php`, assigned per Moderator in `users.moderator_permissions`. Role creation and arbitrary permission creation are not supported.

## Runtime and tooling

The backend uses Laravel 10 and PHP. The frontend uses Vue 3, Vue Router, Pinia, Axios, and Vite. MySQL is selected by `.env.example` and can be configured through the environment. CSS loads through one `resources/css/app.css` entry: tokens, base reset/typography, then route and component styles through Vue SFC imports. See [Database Design](DATABASE_DESIGN.md), [API Documentation](API_DOCUMENTATION.md), and the root [Planora Architecture](../PLANORA_ARCHITECTURE.md) for related details.
