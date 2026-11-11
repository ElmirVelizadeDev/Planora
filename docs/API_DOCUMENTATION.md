# API Documentation

The registered JSON API is versioned under `/api/v1`. Laravel mounts `routes/api/v1.php` through `routes/api.php`. `php artisan route:list` is the source of truth for the active methods and route names.

## Authentication

The first-party SPA uses Sanctum stateful session authentication and CSRF protection; the routes below do not issue a bearer token for browser login. Initialize the CSRF cookie before session-authenticated requests. Public authentication endpoints have request throttles; protected endpoints require the appropriate authenticated, active account and role.

| Method | Endpoint | Access and purpose |
|---|---|---|
| `POST` | `/api/v1/auth/login` | Public, throttled session login |
| `POST` | `/api/v1/auth/register` | Public, throttled registration start |
| `POST` | `/api/v1/auth/register/verify` | Public, throttled registration OTP verification |
| `POST` | `/api/v1/auth/register/resend` | Public, throttled registration OTP resend |
| `POST` | `/api/v1/auth/password/forgot` | Public password-recovery request |
| `POST` | `/api/v1/auth/password/resend` | Public recovery-code resend |
| `POST` | `/api/v1/auth/password/verify` | Public recovery-code verification; issues the recovery cookie |
| `POST` | `/api/v1/auth/password/reset` | Public recovery-session password reset |
| `POST` | `/api/v1/auth/logout` | Authenticated session logout |
| `GET` | `/api/v1/user` | Authenticated active account's public profile |

The local OTP command and verification details are documented in [Registration OTP lifecycle](REGISTRATION_OTP.md) and [Local mail testing](LOCAL_MAIL_TESTING.md).

## Admin APIs

User and task/project Admin routes require an active Admin session except for User Management routes whose policies explicitly permit a moderator permission.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/users` | User listing, filters, pagination, and statistics |
| `POST` | `/api/v1/admin/users` | Create a user (Admin only) |
| `GET` | `/api/v1/admin/users/{user}` | Authorized user details |
| `PATCH` | `/api/v1/admin/users/{user}` | Update a user (Admin only) |
| `POST` | `/api/v1/admin/users/{user}/password` | Change an account password (Admin only) |
| `DELETE` | `/api/v1/admin/users/{user}` | Soft-delete a user (Admin only) |
| `POST` | `/api/v1/admin/users/{userId}/restore` | Restore an authorized deleted user |
| `PATCH` | `/api/v1/admin/users/{user}/role` | Change role (Admin only) |
| `PATCH` | `/api/v1/admin/users/{user}/status` | Change status under User Management authorization |
| `PUT` | `/api/v1/admin/users/{user}/permissions` | Set moderator permissions (Admin only) |
| `GET` | `/api/v1/admin/users/activity` | Authorized activity listing |
| `GET` | `/api/v1/admin/users/roles-permissions` | View role/permission definitions |
| `GET` | `/api/v1/admin/tasks` | Task listing, filters, pagination, and statistics |
| `GET` | `/api/v1/admin/tasks/assignees` | Active normal-user task assignment choices |
| `POST` | `/api/v1/admin/tasks` | Create a Task |
| `GET` | `/api/v1/admin/tasks/{task}` | Read a Task |
| `PATCH` | `/api/v1/admin/tasks/{task}` | Update Task data and assignment |
| `DELETE` | `/api/v1/admin/tasks/{task}` | Soft-delete a Task |
| `GET` | `/api/v1/admin/projects` | Project listing, filters, pagination, and status statistics |
| `GET` | `/api/v1/admin/projects/owners` | Active normal-user Project owner choices |
| `POST` | `/api/v1/admin/projects` | Create a Project |
| `GET` | `/api/v1/admin/projects/{project}` | Read Project details |
| `PATCH` | `/api/v1/admin/projects/{project}` | Update Project data, owner, status, or dates |
| `DELETE` | `/api/v1/admin/projects/{project}` | Delete a Project; linked Tasks remain with `project_id=null` |
| `GET` | `/api/v1/admin/projects/{project}/tasks` | List/filter a Project's Tasks |
| `POST` | `/api/v1/admin/projects/{project}/tasks` | Create a Task linked to a Project |

## User Task and Project APIs

These routes require an active normal User. Task queries are restricted to Tasks owned by or assigned to that User. Project queries are restricted to Projects whose `owner_id` is the current User; Project Task queries apply both the Project and Task scopes.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/tasks` | List owned/assigned Tasks with search, status, priority, due-date, sorting, and pagination filters |
| `POST` | `/api/v1/tasks` | Create a Task owned by the current User |
| `GET` | `/api/v1/tasks/{task}` | Read an owned or assigned Task |
| `PATCH` | `/api/v1/tasks/{task}` | Update an owned Task |
| `PATCH` | `/api/v1/tasks/{task}/status` | Update status of an owned or assigned Task |
| `DELETE` | `/api/v1/tasks/{task}` | Soft-delete an owned Task |
| `GET` | `/api/v1/projects` | List assigned Projects; supports search and lifecycle status filters |
| `GET` | `/api/v1/projects/{project}` | Read an assigned Project |
| `GET` | `/api/v1/projects/{project}/tasks` | List Tasks in the assigned Project that the User owns or is assigned to |

Project lifecycle status values are `planned`, `active`, `on_hold`, and `completed`. Project and Task collection endpoints return `data` and pagination `meta`; Project details return `project`, and Task details use the corresponding Task resource shape. Validation failures use Laravel's JSON validation response.

## Query parameters

- Project collections accept `search`, `status`, `page`, and `per_page`. Project Task collections also accept Task `status`, `priority`, `search`, `page`, and `per_page`.
- Task collections accept search/status/priority and pagination parameters. Admin Task filters also include assignment and date-range filters; User Task filters include due-state and date-range filters.
- Validation and bounds are defined in the corresponding Form Requests under `app/Http/Requests/Api/V1/`. Unsupported or malformed filters return a validation response.

The API does not rely on Vue navigation guards for access control. See [Authentication and Security](AUTHENTICATION_SECURITY.md) for trust boundaries and [Database Design](DATABASE_DESIGN.md) for the Project–Task relationship.
