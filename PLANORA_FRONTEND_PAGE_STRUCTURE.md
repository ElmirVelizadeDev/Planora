# Planora frontend page structure

This document maps active Vue route groups and their implementation boundaries. Routes are declared in `resources/js/router/routes.js`; API and server-side authorization remain authoritative.

## Application entry and layouts

- `resources/views/welcome.blade.php` is the SPA shell and loads the Vite entries `resources/css/app.css` and `resources/js/app.js`.
- `resources/js/app.js` creates Vue, Pinia, and Vue Router. Route components are lazy-loaded.
- `resources/js/layouts/AuthLayout.vue`, `UserLayout.vue`, and `AdminLayout.vue` provide separate authentication, User workspace, and Admin shells.
- `resources/js/layouts/ProfileLayout.vue` reuses the existing User or Admin shell for the one shared `/profile` page used by User, Moderator, and Admin accounts.
- `resources/js/router/guards.js` loads the current session User and applies route role checks. Laravel independently protects API data and mutations.

## Route groups and implementation status

| Area | Route examples | Current implementation |
|---|---|---|
| Public and Auth | `/`, `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, `/terms-and-conditions`, `/privacy-policy` | Public pages, session authentication, registration verification, and password recovery. See [registration OTP lifecycle](docs/REGISTRATION_OTP.md) and [authentication and security](docs/AUTHENTICATION_SECURITY.md). |
| User workspace | `/dashboard`, `/tasks`, `/tasks/:taskId`, `/projects`, `/projects/:projectId` | Dashboard task summary, owned/assigned Task workflows, and Projects owned by the current User. Project Tasks are further scoped by Task ownership/assignment. |
| User Categories | `/categories`, `/categories/:categoryId` | Read-only active Category catalog with search, sorting, pagination, accessible usage counts, and User-scoped related Projects/Tasks. Create/edit paths redirect to the catalog. |
| User notifications | `/notifications` | Persisted notification history for active Users, with unread filtering, pagination, read actions, and authorized task navigation. |
| Shared Global Search | Header search; `GET /api/v1/search` | Debounced, grouped results for entities visible to the current role. Admin and permitted Moderators can search authorized management records; Users receive only owned/assigned Tasks, owned Projects, and active Categories. |
| Other User pages | `/calendar`, `/tags`, `/settings`, `/statistics`, `/productivity` | Routes exist; Calendar, Tags, and several productivity/settings screens remain partial or scaffolded. |
| Appearance | `/settings/appearance` | Authenticated appearance preferences; public navigation also provides persistent guest light/dark theme selection. |
| Shared account profile | `/profile` | Shared profile, personal information, avatar, account details, and password management experience for User, Moderator, and Admin. Email and account deletion remain Admin-managed under existing verification and account-lifecycle rules. |
| Admin workspace | `/admin/dashboard`, `/admin/users`, `/admin/tasks`, `/admin/projects`, `/admin/backups` | Database-backed operational dashboard and account, Task, Project, and backup workflows. Backup & Recovery is Admin-only. Moderators receive only individually permitted views and status actions. Task recovery is available to Admins from the deleted-task filter. |
| Admin Categories | `/admin/categories`, `/admin/categories/create`, `/admin/categories/:categoryId`, `/admin/categories/:categoryId/edit` | Database-backed list and detail; Moderators with `categories.view` are read-only. Create, edit, archive, and restore are Admin-only. |
| Admin reporting and audit | `/admin/reports`, `/admin/activity-logs` | Reports & Analytics is readable by Admins and Moderators with `reports.view`; CSV export is Admin-only. Activity history is searchable and exportable, with Moderator records restricted by permission and account scope. |
| Admin access and settings | `/admin/roles-permissions`, `/admin/settings`, `/admin/system-maintenance` | Fixed role overview with per-Moderator permission assignment; General, Security, Email, and scoped maintenance controls. |
| Admin notifications | `/admin/notifications` | Persisted notification history for active Admins, with unread filtering, pagination, read actions, and authorized task navigation. |
| Errors | `/403`, `/419`, `/429`, `/500`, `/503`, unknown-path fallback | Dedicated error pages are registered. |

The administrator sign-in page is rendered at the server-configured web entry path and selected by a Blade shell marker. It is intentionally not registered in Vue Router.

## Feature structure

- Route-level pages are grouped by application area: `resources/js/pages/public/`, `auth/`, `user/`, `admin/`, `account/`, `profile/`, and `errors/`. `user/` is reserved for pages mounted under the User-only route group. Shared appearance settings remain in `account/` because both User and Admin accounts can reach them through a layout that selects the appropriate workspace shell.

- Authentication: `resources/js/features/auth/` contains the auth store, services, and related UI.
- Profile: `resources/js/features/profile/` contains shared account profile components, API helpers, and the current-account composable. The page uses the existing `UserAvatar` and workspace shells.
- Categories: `resources/js/features/categories/admin/`, `user/`, and `shared/` contain the separate Admin and User UI/API layers and shared Category components. Route pages live in `resources/js/pages/admin/categories/` and `resources/js/pages/user/categories/`.
- Tasks: `resources/js/features/tasks/admin/` and `user/` contain task-specific components, composables, and API services.
- Projects: `resources/js/features/projects/admin/` contains Admin Project components, composables, and API services; User Project API helpers are under `resources/js/features/projects/user/`.
- Global Search: the shared `SearchBar.vue` integrates the frontend service in `resources/js/features/search/services/`; Laravel performs role-aware search and returns Vue Router destinations.
- Admin Backups: `resources/js/pages/admin/backups/` contains the page, with its API client under `resources/js/features/admin/backups/services/` and styles under `resources/css/features/admin/`.
- Shared UI and API: `resources/js/shared/` contains the Axios client, reusable components, and workspace navigation.

## CSS loading and ownership

`resources/css/app.css` is the single global entry and imports tokens, reset, then typography. Layout styles live under `resources/css/layouts/`; reusable styles live under `resources/css/components/`; Categories styles are feature-owned under `resources/css/features/categories/{admin,shared,user}/`; other page styles remain under `resources/css/pages/`. Vue SFC `style src` imports associate page/component styles with their lazy-loaded consumers. The Admin and User Category styles remain separate; the shared Category badge has one stylesheet source.

User and Admin route groups use separate layouts. Moderators can use `AdminLayout` only on routes enabled by their individually assigned permissions: `users.view`, `activity.view`, `categories.view`, `projects.view`, `tasks.view`, or `reports.view`. The dashboard's Users, Activity, Categories, Projects, Tasks, and Reports section flags follow those grants; aggregate data is returned for the first five sections, while Reports uses its separate API. `users.status.manage`, `projects.status.manage`, and `tasks.status.manage` additionally allow only their respective status changes and require the matching view permission. A Moderator role does not grant every permission. Other Admin operations, including Settings, role assignment, create/edit/archive/restore actions, and report CSV export, remain Admin-only. Obsolete Admin Analytics, Roles, Permissions, and Tags URLs redirect to canonical destinations; `/admin/notifications` is an Admin-only notification history page. Legacy User Tag URLs redirect to Categories because no Tag feature exists. Legacy User subtask, Statistics, Productivity, and settings subroutes redirect to existing Task detail, Productivity Reports, notification center, or account settings pages because their former routed components were empty. For API and data scope details, see [API documentation](docs/API_DOCUMENTATION.md) and [database design](docs/DATABASE_DESIGN.md).

The shared header search debounces requests by 300 ms, requires at least two characters, aborts superseded requests, and ignores stale responses. It supports Ctrl/Cmd+K, Escape, arrow-key selection, Enter navigation, and outside-click closing. Mobile uses the existing search overlay rather than a permanently visible input. These interactions do not replace the API's role and record authorization.
