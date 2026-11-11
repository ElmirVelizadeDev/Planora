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
- User tasks are scoped to the authenticated User as owner or assignee. User Projects are scoped to the current User's Project owner assignment; Project Task queries additionally apply the Task owner/assignee rule.
- Admin Project and Task APIs are restricted to active Admin sessions. Admin user-management APIs have their own Admin/moderator permission checks.
- A Task may be standalone or linked to a Project. The nullable foreign key preserves the Task when its Project is deleted by setting `project_id` to null.

## Runtime and tooling

The backend uses Laravel 10 and PHP. The frontend uses Vue 3, Vue Router, Pinia, Axios, and Vite. MySQL is selected by `.env.example` and can be configured through the environment. See [Database Design](DATABASE_DESIGN.md), [API Documentation](API_DOCUMENTATION.md), and the root [Planora Architecture](../PLANORA_ARCHITECTURE.md) for related details.
