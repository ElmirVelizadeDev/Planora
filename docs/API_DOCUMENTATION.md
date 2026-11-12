# API Documentation

The active JSON API is versioned under `/api/v1`. Laravel mounts `routes/api/v1.php` through `routes/api.php`; `php artisan route:list` is the authoritative route inventory. The first-party Vue SPA uses Sanctum stateful sessions and CSRF protection, not bearer tokens for browser login.

Protected routes require an authenticated active account and the role or policy described below. Vue route guards are not an API security boundary.

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

See [Authentication and Security](AUTHENTICATION_SECURITY.md), [Registration OTP lifecycle](REGISTRATION_OTP.md), and [Local mail testing](LOCAL_MAIL_TESTING.md) for the authentication flow and development tooling.

## Admin APIs

Admin Project, Task, and Category routes require `auth:sanctum`, active-account middleware, and the `admin` role. User Management routes also allow only the moderator permissions explicitly granted by their policy/request checks.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/users` | User listing, filters, pagination, and statistics |
| `POST` | `/api/v1/admin/users` | Create a User (Admin only) |
| `GET` | `/api/v1/admin/users/{user}` | Authorized User details |
| `PATCH` | `/api/v1/admin/users/{user}` | Update a User (Admin only) |
| `POST` | `/api/v1/admin/users/{user}/password` | Change an account password (Admin only) |
| `DELETE` | `/api/v1/admin/users/{user}` | Soft-delete a User (Admin only) |
| `POST` | `/api/v1/admin/users/{userId}/restore` | Restore an authorized deleted User |
| `PATCH` | `/api/v1/admin/users/{user}/role` | Change role (Admin only) |
| `PATCH` | `/api/v1/admin/users/{user}/status` | Change status under User Management authorization |
| `PUT` | `/api/v1/admin/users/{user}/permissions` | Set moderator permissions (Admin only) |
| `GET` | `/api/v1/admin/users/activity` | Authorized activity listing |
| `GET` | `/api/v1/admin/users/roles-permissions` | Role and permission definitions |
| `GET` | `/api/v1/admin/tasks` | Task listing, filters, pagination, and global statistics |
| `GET` | `/api/v1/admin/tasks/assignees` | Active normal-User assignment choices |
| `POST` | `/api/v1/admin/tasks` | Create a Task |
| `GET` | `/api/v1/admin/tasks/{task}` | Read Task details |
| `PATCH` | `/api/v1/admin/tasks/{task}` | Update Task data and assignment |
| `DELETE` | `/api/v1/admin/tasks/{task}` | Soft-delete a Task |
| `GET` | `/api/v1/admin/projects` | Project listing, filters, pagination, and status statistics |
| `GET` | `/api/v1/admin/projects/owners` | Active normal-User Project owner choices |
| `POST` | `/api/v1/admin/projects` | Create a Project |
| `GET` | `/api/v1/admin/projects/{project}` | Read Project details |
| `PATCH` | `/api/v1/admin/projects/{project}` | Update Project data, owner, status, dates, or Category |
| `DELETE` | `/api/v1/admin/projects/{project}` | Delete a Project; linked Tasks are detached |
| `GET` | `/api/v1/admin/projects/{project}/tasks` | List and filter Project Tasks |
| `POST` | `/api/v1/admin/projects/{project}/tasks` | Create a Task linked to a Project |
| `GET` | `/api/v1/admin/categories` | List active, archived, or all Categories with global statistics |
| `POST` | `/api/v1/admin/categories` | Create a Category |
| `GET` | `/api/v1/admin/categories/{category}` | Read Category details and global related Projects/Tasks |
| `PATCH` | `/api/v1/admin/categories/{category}` | Update a Category |
| `DELETE` | `/api/v1/admin/categories/{category}` | Archive a Category (soft delete) |
| `POST` | `/api/v1/admin/categories/{category}/restore` | Restore an archived Category |

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

For table relationships and deletion behavior, see [Database Design](DATABASE_DESIGN.md). For access boundaries, see [Authentication and Security](AUTHENTICATION_SECURITY.md).
