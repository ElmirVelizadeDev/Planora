# Planora documentation

This index groups the maintained technical documentation by topic. The root [README](../README.md) introduces the project and provides the quick-start path.

## API

- [API documentation](api/API_DOCUMENTATION.md) — versioned routes, access requirements, request and response details.

## Architecture and data

- [Planora architecture](architecture/PLANORA_ARCHITECTURE.md) — application boundaries, trust model, and current implementation scope.
- [System architecture](architecture/SYSTEM_ARCHITECTURE.md) — request flow, runtime behavior, operations, and tooling.
- [Frontend page structure](architecture/FRONTEND_PAGE_STRUCTURE.md) — Vue routes, page groups, features, and CSS ownership.
- [Database design](architecture/DATABASE_DESIGN.md) — tables, relationships, persistence, migrations, and demo data.

## Security

- [Authentication and security](security/AUTHENTICATION_SECURITY.md) — sessions, MFA, authorization, account controls, and security operations.
- [Registration OTP lifecycle](security/REGISTRATION_OTP.md) — code storage, verification, expiry, and cleanup scheduling.

## Operations

- [Administrator access and maintenance](operations/ADMIN_ACCESS_AND_MAINTENANCE.md) — administrator entry, maintenance scopes, and deployment operations.
- [Backups and disaster recovery](operations/BACKUPS_AND_DISASTER_RECOVERY.md) — archive configuration, restore workflow, and operational limits.
- [Error handling and maintenance](operations/ERROR_HANDLING_AND_MAINTENANCE.md) — HTTP/UI errors, maintenance behavior, and deployment guidance.

## Development

- [Local authentication-code testing](development/LOCAL_MAIL_TESTING.md) — local OTP workflows without SMTP.
- [Local demo seeders](development/LOCAL_DEMO_SEEDERS.md) — fixture behavior and database safety restrictions.

## Project

- [Project overview](project/PROJECT_OVERVIEW.md) — implemented workflows and product boundaries.
- [Development roadmap](project/DEVELOPMENT_ROADMAP.md) — current implementation scope and planned work boundaries.

## Testing

- [Isolated MySQL test database](testing/TESTING_DATABASE.md) — safe PHPUnit database requirements.
