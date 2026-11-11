# Planora Project Overview

Planora is a Laravel and Vue single-page application for personal productivity and project coordination. It combines authenticated User workspaces with an Admin Panel for account, Task, and Project management.

## Implemented workflows

- Authentication uses Laravel sessions with Sanctum's stateful SPA middleware. Registration and password recovery use time-limited email OTP flows; local development can issue codes from a protected Artisan command.
- Users can create and manage Tasks they own, view Tasks assigned to them, and update status when authorized.
- Admins can manage platform Tasks and Projects. Project records have an owner, lifecycle status, optional start/due dates, and related Tasks.
- Users can view Projects assigned by an Admin, inspect project details, and access only Tasks in that Project that they own or are assigned to.
- User and Admin account management applies server-side validation and authorization. Moderator permissions apply to selected user-management APIs, but moderators cannot access the Admin Vue layout.

## Product boundaries

The database-backed feature areas are authentication/account management, Tasks, and Projects. Category, calendar, tags, notifications, settings, reports, and several Admin destinations remain partial or scaffolded; the existence of a Vue route does not mean its persistent workflow is complete.

Project Tasks and standalone Tasks share the Task model and API authorization rules. A Project deletion detaches its Tasks through the nullable database relationship; it does not delete those Task records.

## Further reading

- [System Architecture](SYSTEM_ARCHITECTURE.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Database Design](DATABASE_DESIGN.md)
- [Authentication Security](AUTHENTICATION_SECURITY.md)
- [Development Roadmap](DEVELOPMENT_ROADMAP.md)
