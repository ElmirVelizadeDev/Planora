# Development Roadmap

This document records implementation boundaries visible in the current repository. It is a scope guide, not a release schedule.

## Implemented foundation

- Session/Sanctum authentication, registration verification, password recovery, active-account checks, and role-aware routing.
- Admin account management, user activity APIs, and Admin/User Task workflows.
- Admin Project management and User Projects, including owner assignment, lifecycle status, dates, Project Task management, and User-scoped Project/Task access.
- Local-only idempotent demo seeders for Users, Projects, linked Project Tasks, and standalone Tasks.

## Remaining incomplete areas

- Complete persistent workflows for category, calendar, tag, notification, settings, reports, and related productivity routes.
- Complete the remaining Admin destinations, including role/permission UI, category/tag management, reports, notifications, and settings.
- Resolve the mismatch between limited moderator API permissions and the Admin Vue layout, which currently admits only admins.
- Expand automated coverage and browser verification for the current feature set.

Do not treat a route, placeholder component, or unverified external mail configuration as a finished workflow. Current APIs and schema are listed in [API Documentation](API_DOCUMENTATION.md) and [Database Design](DATABASE_DESIGN.md).
