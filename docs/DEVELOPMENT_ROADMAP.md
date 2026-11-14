# Development Roadmap

This document records implementation boundaries visible in the current repository. It is a scope guide, not a release schedule.

## Implemented foundation

- Session/Sanctum authentication, registration verification, password recovery, active-account checks, and role-aware routing.
- Admin account management, user activity APIs, and Admin/User Task workflows.
- Admin Project management and User Projects, including owner assignment, lifecycle status, dates, Project Task management, and User-scoped Project/Task access.
- Admin Category management and a read-only User Category catalog. Categories are optionally assigned to Projects and Tasks; archive/restore retains associations and User category detail is scoped to accessible records.
- Consolidated Admin Roles & Permissions management for the fixed per-Moderator permission catalog, with Admin-only assignment and audit events.
- Role-aware Admin Dashboard aggregates, unified Reports & Analytics, searchable Activity Logs, and General/Security/Email settings.
- Admin Task soft-delete recovery with an audited restore flow; Project deletion remains permanent and detaches linked Tasks.
- Local-only idempotent demo seeders for Categories, Projects, linked Project Tasks, and standalone Tasks. The account seeder is invoked unconditionally by `DatabaseSeeder`; do not use `db:seed` or `migrate --seed` outside a disposable local database. See [Seeder Notes](../database/seeders/README.md).

## Remaining incomplete areas

- Complete remaining persistent workflows for User Calendar, Tags, Notifications, and related productivity routes as product scope requires.
- Add additional Moderator module permissions only when business purpose, object-level rules, backend authorization, UI behavior, and tests are defined together.
- Expand automated coverage and browser verification for the current feature set.

Do not treat a route, placeholder component, or unverified external mail configuration as a finished workflow. Current APIs and schema are listed in [API Documentation](API_DOCUMENTATION.md) and [Database Design](DATABASE_DESIGN.md).
