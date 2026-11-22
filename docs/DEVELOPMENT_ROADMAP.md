# Development roadmap

This document records implementation boundaries visible in the current repository. It is a scope guide, not a release schedule.

## Completed

- Session/Sanctum authentication, registration verification, password recovery, active-account checks, and role-aware routing.
- Admin account management, user activity APIs, and Admin/User Task workflows.
- Admin Project management and User Projects, including owner assignment, lifecycle status, dates, Project Task management, and User-scoped Project/Task access.
- Admin Category management and a read-only User Category catalog. Categories are optionally assigned to Projects and Tasks; archive/restore retains associations and User category detail is scoped to accessible records.
- Consolidated Admin Roles & Permissions management for the fixed per-Moderator permission catalog, with Admin-only assignment and audit events.
- Role-aware Admin Dashboard aggregates, unified Reports & Analytics, searchable Activity Logs, and General/Security/Email settings.
- Admin Task soft-delete recovery with an audited restore flow; Project deletion remains permanent and detaches linked Tasks.
- Server-configured administrator entry, independently enforced maintenance scopes, and persisted User/Admin notifications for task and administrative events.
- Role-aware Global Search backed by scoped database queries, and Admin-only encrypted Backup & Recovery with verification, retention, and guarded restore.
- Optional Admin authenticator-based MFA, single-use recovery codes, and one-use step-up verification for sensitive Admin actions.
- Account appearance preferences and guest light/dark theme persistence.
- Local-only idempotent demo seeders for Categories, Projects, linked Project Tasks, and standalone Tasks. The account seeder is invoked unconditionally by `DatabaseSeeder`; do not use `db:seed` or `migrate --seed` outside a disposable local database. See [seeder notes](../database/seeders/README.md).

## Planned

- Define whether a dedicated Tag workflow is required; current Tag URLs redirect to Categories and there is no Tag model or API. Extend productivity reporting only when product requirements are defined; the current User productivity report is API-backed. Notifications have persisted APIs and history views, while notification-preference screens are not a separate implemented workflow.
- Add additional Moderator module permissions only when business purpose, object-level rules, backend authorization, UI behavior, and tests are defined together.
- Expand automated coverage and browser verification for the current feature set.

Do not treat a route, placeholder component, or unverified external mail configuration as a finished workflow. Current APIs and schema are listed in [API documentation](API_DOCUMENTATION.md) and [database design](DATABASE_DESIGN.md).
