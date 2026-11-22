# Planora project overview

Planora is a Laravel 10 and Vue 3 single-page project-management application. It combines authenticated User workspaces with an Admin Panel for account, Task, Project, and Category management.

## Implemented workflows

- Sanctum stateful-session authentication, verified registration, password recovery, and active-account restrictions.
- Admin and User account management subject to server-side authorization and moderator permission rules.
- Admin and User Task workflows with ownership/assignment scopes, status and priority, due dates, and optional Project and Category associations.
- Admin Project management and User Project views scoped to Projects owned by the signed-in User.
- Admin-managed Categories shared by Projects and Tasks, plus a read-only User catalog of active categories.
- Category archive/restore preserves Project and Task associations. User category details expose only owned Projects and owned/assigned Tasks; Admin usage counts are global.
- Persisted, recipient-scoped User/Admin notifications for task events and administrative events, including unread counts and read-state actions.
- Role-aware Global Search for authorized Users, Tasks, Projects, and Categories, with User record scoping applied by Laravel.
- Admin-only encrypted database, managed-file, and full backups, with archive verification, history, retention, and a safety-backed restore workflow. See [backup and disaster recovery](BACKUPS_AND_DISASTER_RECOVERY.md).
- Three independently enforced maintenance scopes, an emergency Laravel-wide maintenance mode, and a server-configured administrator sign-in route.
- Optional administrator authenticator-based MFA, single-use recovery codes, and purpose-bound step-up verification for sensitive Admin operations.
- Account appearance preferences and a persistent guest light/dark theme.
- Local-only Category, Project, and User Task demo fixtures. See [seeder notes](../database/seeders/README.md) for important environment restrictions.

## Product boundaries

The User Calendar supports month, week, day, and agenda views using authorized Task date ranges. User productivity reports, profile, notifications, and appearance preferences have implemented workflows. Tags are not a separate feature: legacy Tag routes redirect to Categories. Some legacy settings routes redirect to current account or notification pages rather than representing separate settings APIs. User and Admin notifications, Admin reports, Admin settings, and Admin Backup & Recovery have persisted API-backed workflows. Global Search queries existing application records; it does not introduce search-specific persistence. A Vue route alone does not imply a complete persisted workflow. Moderators can use `AdminLayout` only for pages enabled by individually assigned permissions; the role does not automatically grant access to every Admin page. The permission catalog includes selected User, Activity, Category, Project, Task, and Reports views, with separate grants required for status changes. Dashboard section flags follow those view permissions, with report data provided by the separate report endpoints. Settings, backup management, and role assignment remain Admin-only; other full management operations and report CSV export are Admin-only apart from explicitly permitted status changes. See [authentication and security](AUTHENTICATION_SECURITY.md) for permission details.

Project-linked and standalone Tasks share the Task model. Deleting a Project detaches its Tasks through nullable `project_id`; archiving a Category retains its associations. See [system architecture](SYSTEM_ARCHITECTURE.md), [API documentation](API_DOCUMENTATION.md), and [database design](DATABASE_DESIGN.md) for implementation details.
