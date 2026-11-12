# Planora Project Overview

Planora is a Laravel 10 and Vue 3 single-page project-management application. It combines authenticated User workspaces with an Admin Panel for account, Task, Project, and Category management.

## Implemented workflows

- Sanctum stateful-session authentication, verified registration, password recovery, and active-account restrictions.
- Admin and User account management subject to server-side authorization and moderator permission rules.
- Admin and User Task workflows with ownership/assignment scopes, status and priority, due dates, and optional Project and Category associations.
- Admin Project management and User Project views scoped to Projects owned by the signed-in User.
- Admin-managed Categories shared by Projects and Tasks, plus a read-only User catalog of active categories.
- Category archive/restore preserves Project and Task associations. User category details expose only owned Projects and owned/assigned Tasks; Admin usage counts are global.
- Local-only Category, Project, and User Task demo fixtures. See [Seeder Notes](../database/seeders/README.md) for important environment restrictions.

## Product boundaries

Calendar, tags, notifications, settings, reports, and several Admin destinations remain partial or scaffolded. A Vue route does not imply a complete persisted workflow. Moderator APIs permit selected User Management operations, but moderators do not have access to the Admin Vue layout.

Project-linked and standalone Tasks share the Task model. Deleting a Project detaches its Tasks through nullable `project_id`; archiving a Category retains its associations. See [System Architecture](SYSTEM_ARCHITECTURE.md), [API Documentation](API_DOCUMENTATION.md), and [Database Design](DATABASE_DESIGN.md) for implementation details.
