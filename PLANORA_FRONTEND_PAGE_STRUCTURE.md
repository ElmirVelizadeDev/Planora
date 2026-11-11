# Planora Frontend Page Structure

This is a current map of the Vue application, its route groups, and implementation boundaries. Routes are declared in `resources/js/router/routes.js`; only the backend and connected UI determine whether a workflow is implemented.

## Application entry and layouts

- `resources/views/welcome.blade.php` provides the single SPA mount point and Vite assets.
- `resources/js/app.js` creates Vue, Pinia, and Vue Router. Route components are lazy-loaded.
- `resources/js/layouts/AuthLayout.vue`, `UserLayout.vue`, and `AdminLayout.vue` provide the public authentication, User workspace, and Admin shell.
- `resources/js/router/guards.js` loads the current session User when needed and applies route role checks. Laravel remains the authorization boundary.

## Route groups and implementation status

| Area | Route examples | Current implementation |
|---|---|---|
| Public and Auth | `/`, `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, `/terms-and-conditions`, `/privacy-policy` | Public pages and authentication flows are implemented; OTP and session details are in [Registration OTP](docs/REGISTRATION_OTP.md) and [Authentication Security](docs/AUTHENTICATION_SECURITY.md). |
| User workspace | `/dashboard`, `/tasks`, `/tasks/:taskId`, `/projects`, `/projects/:projectId` | Dashboard task summary, User Task workflows, and assigned User Projects are connected to APIs. Project Task access is scoped by Project ownership and Task ownership/assignment. |
| Other User pages | `/calendar`, `/categories`, `/tags`, `/notifications`, `/profile`, `/settings`, `/statistics`, `/productivity` | Route records and page components exist, but these domains are partial or scaffolded; do not infer persistent workflows from their presence. |
| Admin workspace | `/admin/dashboard`, `/admin/users`, `/admin/tasks`, `/admin/projects` | Dashboard/account management, task management, and Project management have database-backed workflows. User management detail/edit interactions use the existing interface flows. |
| Other Admin pages | `/admin/roles`, `/admin/categories`, `/admin/tags`, `/admin/reports`, `/admin/analytics`, `/admin/notifications`, `/admin/activity-logs`, `/admin/settings` | Routes and some UI are present; complete backend workflows are not implemented for every destination. |
| Errors | `/403`, `/419`, `/429`, `/500`, `/503`, unknown-path fallback | Dedicated error pages are registered. |

## Feature and shared code

- Authentication: `resources/js/features/auth/` contains the auth store, services, and related UI.
- Tasks: `resources/js/features/tasks/user/` and `resources/js/features/tasks/admin/` contain task-specific components, composables, and API helpers.
- Projects: `resources/js/features/projects/admin/` contains Admin Project components, composables, and API helpers; User Project API helpers are under `resources/js/features/projects/user/`.
- Shared UI and API: `resources/js/shared/` contains the Axios client, reusable components, and workspace navigation.
- Styles: `resources/css/` contains global tokens/base styles, layout styles, shared component styles, and feature/page styles.

## Navigation and access

The User and Admin route groups use separate layouts and role metadata. The current route guard grants the Admin layout only to admins; moderator permissions for limited user-management APIs do not grant moderator access to the Admin SPA. Protected API endpoints must enforce access independently of the route guard.

For the backend API and its authorization behavior, see [API Documentation](docs/API_DOCUMENTATION.md). For the current work boundary, see [Development Roadmap](docs/DEVELOPMENT_ROADMAP.md).
