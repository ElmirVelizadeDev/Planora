# Planora

**Plan your day. Shape your future.**

Planora is a project and task management application built with Laravel 10 and Vue 3. It combines session-authenticated User workspaces with an Admin panel for account and platform operations.

## Capabilities

- User Tasks and Projects, with owner/assignee access rules, due dates, statuses, priorities, and optional Category associations.
- Admin account, Task, Project, and Category management, plus reports, activity logs, dashboard data, and platform settings.
- Role-aware Global Search across records each signed-in User, Moderator, or Admin may access.
- Admin-only encrypted database, managed-file, and full backups with integrity verification and a guarded restore workflow.
- Session authentication with verified registration, one-time-code password recovery, active-account checks, and role-based authorization.
- A server-configured administrator sign-in route, independent maintenance scopes, and recipient-scoped notifications for task and administrative events.
- Per-account appearance preferences and persistent guest light/dark theme selection.

User Category details and related-record counts are limited to Projects owned by the signed-in User and Tasks owned by or assigned to that User; Admin usage counts are global. Calendar, Tags, and some productivity and settings screens remain partial or scaffolded. A Vue route alone does not imply a persisted, API-backed workflow.

## Technology stack

| Area | Technology |
|---|---|
| Backend | PHP `^8.1`, Laravel `^10.10` (locked to 10.50.4), MySQL |
| Frontend | Vue `^3.5`, Vue Router `^4.6`, Pinia `^3` |
| API and authentication | Versioned `/api/v1` JSON API, Sanctum stateful sessions, Axios |
| Assets | Vite `^5`, `@vitejs/plugin-vue` `^6` |

## Architecture

Laravel serves the Vue single-page application shell and versioned JSON API. Vue Router lazy-loads pages into separate authentication, Admin, and User layouts. Backend middleware, Form Requests, policies, services, and record-scoped queries enforce access; client route guards provide navigation behavior only.

The frontend is organized under `resources/js/features/`, `resources/js/shared/`, and `resources/js/pages/`. The shared workspace header hosts Global Search; the Admin Backup page uses its own API service and feature styles. Global CSS is loaded from `resources/css/app.css`; Vue single-file component style imports keep page and component styles with their consumers.

For implementation details, see [Planora architecture](PLANORA_ARCHITECTURE.md), [system architecture](docs/SYSTEM_ARCHITECTURE.md), and [frontend page structure](PLANORA_FRONTEND_PAGE_STRUCTURE.md).

## Local setup

Requirements: PHP 8.1 or later compatible with the Composer constraints, Composer, Node.js `^20.19.0` or `>=22.12.0`, npm, and MySQL. The Node.js constraint comes from the locked Vue Vite plugin.

```sh
composer install
npm ci
```

Copy `.env.example` to `.env`, set `APP_URL`, and configure `DB_CONNECTION=mysql` with the host, port, database name, username, and password for a local MySQL database. Create that database, then generate the application key, migrate the schema, and link public storage:

```sh
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

For local administrator access, set `PLANORA_ADMIN_ENTRY_PATH` to a generated URL-safe value of at least 48 characters. Production requires this setting. Follow [administrator access and maintenance](docs/ADMIN_ACCESS_AND_MAINTENANCE.md) to generate and deploy it securely.

Start the frontend development server and Laravel application in separate terminals:

```sh
npm run dev
php artisan serve
```

Open the URL printed by `php artisan serve`, or use the configured local web server. To run scheduled tasks locally, start `php artisan schedule:work` in another terminal. Use `npm run build` to compile production assets.

### Demo data safety

Review [seeder notes](database/seeders/README.md) before seeding. `CategorySeeder`, `ProjectDemoSeeder`, and `UserTasksDemoSeeder` are guarded to `APP_ENV=local`.

> **Warning:** `DatabaseSeeder` also invokes `PlanoraUsersSeeder` without an environment guard. Do not run `php artisan db:seed` or `php artisan migrate --seed` against production or any database containing non-disposable data.

For development, verify `.env` points to the intended local database first. Seeder behavior and preservation limits are described in the seeder notes.

## Testing

Feature tests require an isolated MySQL test database. The test bootstrap rejects non-MySQL connections and database names without a standalone `test` or `testing` segment. Configure `.env.testing` using a dedicated database and user; never point tests at the development database. See [testing database](docs/TESTING_DATABASE.md).

```sh
php artisan test
node --experimental-test-module-mocks --test tests/js/*.test.js
npm run build
```

PHPUnit feature tests require the isolated MySQL database configured in `.env.testing`. JavaScript tests use Node's built-in test runner with Node's experimental module-mocking flag; no separate test script is defined in `package.json`. A production build does not replace either test suite.

At this documentation audit, the JavaScript suite reported 30 passing tests and one failure: `tests/js/error-pages.test.js` expects `public/js/error-page.js`, which is absent from the repository, while `resources/views/errors/page.blade.php` references that path. This task did not change the error-page implementation or test.

## Documentation

- [API documentation](docs/API_DOCUMENTATION.md)
- [Authentication and security](docs/AUTHENTICATION_SECURITY.md)
- [Administrator access and maintenance](docs/ADMIN_ACCESS_AND_MAINTENANCE.md)
- [Database design](docs/DATABASE_DESIGN.md)
- [System architecture](docs/SYSTEM_ARCHITECTURE.md)
- [Development roadmap](docs/DEVELOPMENT_ROADMAP.md)
- [Registration OTP lifecycle](docs/REGISTRATION_OTP.md)
- [Local mail testing](docs/LOCAL_MAIL_TESTING.md)
- [Testing database](docs/TESTING_DATABASE.md)
- [Error handling and maintenance operations](docs/ERROR_HANDLING_AND_MAINTENANCE.md)
- [Backup and disaster recovery](docs/BACKUPS_AND_DISASTER_RECOVERY.md)
- [Local demo seeders](database/seeders/README.md)

The project declares the MIT license in `composer.json`.
