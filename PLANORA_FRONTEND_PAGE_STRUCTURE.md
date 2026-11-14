# Planora Frontend Page Structure

This document maps active Vue route groups and their implementation boundaries. Routes are declared in `resources/js/router/routes.js`; API and server-side authorization remain authoritative.

## Application entry and layouts

- `resources/views/welcome.blade.php` is the SPA shell and loads the Vite entries `resources/css/app.css` and `resources/js/app.js`.
- `resources/js/app.js` creates Vue, Pinia, and Vue Router. Route components are lazy-loaded.
- `resources/js/layouts/AuthLayout.vue`, `UserLayout.vue`, and `AdminLayout.vue` provide separate authentication, User workspace, and Admin shells.
- `resources/js/router/guards.js` loads the current session User and applies route role checks. Laravel independently protects API data and mutations.

## Route groups and implementation status

| Area | Route examples | Current implementation |
|---|---|---|
| Public and Auth | `/`, `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, `/terms-and-conditions`, `/privacy-policy` | Public pages, session authentication, registration verification, and password recovery. See [Registration OTP](docs/REGISTRATION_OTP.md) and [Authentication Security](docs/AUTHENTICATION_SECURITY.md). |
| User workspace | `/dashboard`, `/tasks`, `/tasks/:taskId`, `/projects`, `/projects/:projectId` | Dashboard task summary, owned/assigned Task workflows, and Projects owned by the current User. Project Tasks are further scoped by Task ownership/assignment. |
| User Categories | `/categories`, `/categories/:categoryId` | Read-only active Category catalog with search, sorting, pagination, accessible usage counts, and User-scoped related Projects/Tasks. Create/edit paths redirect to the catalog. |
| Other User pages | `/calendar`, `/tags`, `/notifications`, `/profile`, `/settings`, `/statistics`, `/productivity` | Routes and UI exist; several workflows remain partial or scaffolded. |
| Admin workspace | `/admin/dashboard`, `/admin/users`, `/admin/tasks`, `/admin/projects` | Database-backed operational dashboard, account, Task, and Project management workflows. Task recovery is available from the deleted-task filter. |
| Admin Categories | `/admin/categories`, `/admin/categories/create`, `/admin/categories/:categoryId`, `/admin/categories/:categoryId/edit` | Database-backed list, detail, create, edit, archive, and restore. Category management is Admin-only. |
| Admin reporting and audit | `/admin/reports`, `/admin/activity-logs` | Unified Reports & Analytics, plus searchable/exportable activity and management audit history. |
| Admin access and settings | `/admin/roles-permissions`, `/admin/settings` | Fixed role overview with per-Moderator permission assignment; General, Security, and Email settings sections. |
| Errors | `/403`, `/419`, `/429`, `/500`, `/503`, unknown-path fallback | Dedicated error pages are registered. |

## Feature structure

- Authentication: `resources/js/features/auth/` contains the auth store, services, and related UI.
- Categories: `resources/js/features/categories/admin/`, `user/`, and `shared/` contain the separate Admin and User UI/API layers and shared Category components. Route pages live in `resources/js/pages/admin/categories/` and `resources/js/pages/app/categories/`.
- Tasks: `resources/js/features/tasks/admin/` and `user/` contain task-specific components, composables, and API services.
- Projects: `resources/js/features/projects/admin/` contains Admin Project components, composables, and API services; User Project API helpers are under `resources/js/features/projects/user/`.
- Shared UI and API: `resources/js/shared/` contains the Axios client, reusable components, and workspace navigation.

## CSS loading and ownership

`resources/css/app.css` is the single global entry and imports tokens, reset, then typography. Layout styles live under `resources/css/layouts/`; reusable styles live under `resources/css/components/`; Categories styles are feature-owned under `resources/css/features/categories/{admin,shared,user}/`; other page styles remain under `resources/css/pages/`. Vue SFC `style src` imports associate page/component styles with their lazy-loaded consumers. The Admin and User Category styles remain separate; the shared Category badge has one stylesheet source.

User and Admin route groups use separate layouts. Moderators can reach only Dashboard, Users, and Activity Logs when their individual permissions allow it; other Admin pages remain Admin-only. Obsolete Admin Analytics, Roles, Permissions, Tags, and Notifications URLs redirect to their canonical Admin destinations. User-side Tags and Notifications routes are unchanged. For API and data scope details, see [API Documentation](docs/API_DOCUMENTATION.md) and [Database Design](docs/DATABASE_DESIGN.md).
