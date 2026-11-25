# Planora

**Plan your day. Shape your future.**

Planora is a project and task management application built with Laravel 10 and Vue 3. Laravel serves the Vue single-page application and a versioned JSON API; the first-party SPA uses Sanctum stateful sessions.

## Capabilities

- User task and project workspaces with ownership-aware access, calendars, reports, and notifications.
- Admin account, role and permission, task, project, category, reporting, settings, maintenance, and backup workflows.
- Individually permissioned Moderator access, server-side authorization, and mandatory administrator MFA.
- Registration verification, password recovery, activity logging, global search, and account appearance preferences.

## Technology

| Area | Stack |
|---|---|
| Backend | PHP `^8.1`, Laravel `^10.10`, MySQL |
| Frontend | Vue `^3.5`, Vue Router `^4.6`, Pinia `^3`, Axios |
| Build | Vite `^5`, Laravel Vite plugin, Vue plugin for Vite |
| API authentication | Versioned `/api/v1` API, Sanctum stateful sessions |

## Quick start

Requirements: PHP 8.1 or later compatible with Composer constraints, Composer, Node.js `^20.19.0` or `>=22.12.0`, npm, and MySQL.

```sh
composer install
npm ci
```

Copy `.env.example` to `.env` (`Copy-Item .env.example .env` in PowerShell), configure the application URL and a local MySQL database, then run:

```sh
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run dev
php artisan serve
```

Configure `PLANORA_ADMIN_ENTRY_PATH` with a generated URL-safe value of at least 48 characters; production requires it. See [administrator access and maintenance](docs/operations/ADMIN_ACCESS_AND_MAINTENANCE.md). Review the [local demo seeder guide](docs/development/LOCAL_DEMO_SEEDERS.md) before running any seeder: `DatabaseSeeder` invokes an account seeder without an environment guard.

## Testing

Feature tests require a dedicated MySQL test database configured through `.env.testing`; never point them at a development or production database. See [testing setup](docs/testing/TESTING_DATABASE.md).

```sh
php artisan test
node --experimental-test-module-mocks --test tests/js/*.test.js
npm run build
```

## Documentation

Browse the [documentation index](docs/README.md) for API, architecture, security, operations, development, project scope, and testing guides.

Planora declares the MIT license in `composer.json`.
