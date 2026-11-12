# Planora

**Plan your day. Shape your future.**

Planora is a Laravel 10 and Vue 3 single-page project-management application. It provides session-based authentication, separate Admin and User workspaces, account administration, Projects, Tasks, and a shared Category catalog.

## Implemented features

- Sanctum stateful SPA authentication, registration verification, password recovery, account-status checks, and role-based access.
- Admin account management, dashboard statistics, user activity, and protected account operations.
- Admin and User Task workflows, including ownership/assignment rules, statuses, priorities, due dates, and standalone or Project-linked Tasks.
- Admin Project management and User Projects limited to Projects owned by the signed-in User.
- Admin Category management and a read-only User Category catalog. Projects and Tasks may each reference one optional Category. Archiving preserves existing associations; archived categories are hidden from User catalogs and new selections.
- Local-only, idempotent Category, Project, and User Task demo seeders.

User Category details and related-record counts are scoped to Projects owned by the signed-in User and Tasks owned by or assigned to that User. Admin category usage counts are global. See [API Documentation](docs/API_DOCUMENTATION.md) and [Database Design](docs/DATABASE_DESIGN.md).

Calendar, tags, notifications, settings, reports, and several Admin destinations remain partial or scaffolded. A route alone does not indicate that a workflow is backed by a complete API. Moderator permissions exist for selected User Management APIs, but the Vue Admin layout is limited to Admins.

## Technology and architecture

| Area | Technology |
|---|---|
| Backend | PHP `^8.1`, Laravel `^10.10`, MySQL |
| Frontend | Vue `^3.5`, Vue Router `^4.6`, Pinia `^3` |
| API and authentication | Versioned `/api/v1` JSON API, Sanctum stateful sessions, Axios |
| Assets | Vite `^5`, `@vitejs/plugin-vue` |

Laravel serves the SPA shell from `resources/views/welcome.blade.php`. Vue Router lazy-loads pages into separate Auth, Admin, and User layouts. Backend middleware, Form Requests, policies, services, and record-scoped queries enforce access; route guards are navigation support only.

The frontend uses feature folders under `resources/js/features/`, shared UI under `resources/js/shared/`, and route pages under `resources/js/pages/`. CSS has one global entry at `resources/css/app.css`, which loads tokens and base reset/typography styles. Layout and shared component styles are separately owned; Categories styles are organized under `resources/css/features/categories/{admin,shared,user}/`; remaining page-specific styles stay under `resources/css/pages/`. Vue SFC `style src` imports keep page and component styles lazy with their consumers.

For the detailed code map, see [Planora Architecture](PLANORA_ARCHITECTURE.md), [System Architecture](docs/SYSTEM_ARCHITECTURE.md), and [Frontend Page Structure](PLANORA_FRONTEND_PAGE_STRUCTURE.md).

## Local setup

Requirements: PHP 8.1 or later compatible with the Composer constraints, Composer, Node.js `^20.19.0` or `>=22.12.0`, npm, and MySQL.

```sh
composer install
npm ci
```

Copy `.env.example` to `.env`, configure the local URL and database, then run:

```sh
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run dev
```

In a second terminal, run `php artisan serve` if the project is not served through a local web environment. Open the URL provided by the local server. Use `npm run build` to build assets for production.

### Demo data safety

Review [Seeder Notes](database/seeders/README.md) before seeding. `CategorySeeder`, `ProjectDemoSeeder`, and `UserTasksDemoSeeder` are guarded to `APP_ENV=local`. **`DatabaseSeeder` also invokes `PlanoraUsersSeeder` without an environment guard. Do not run `php artisan db:seed` or `php artisan migrate --seed` against production or any database containing non-disposable data.** For development, verify `.env` points to the intended local database first. Seeder behavior and preservation limits are described in the seeder notes.

## Testing

Feature tests require an isolated MySQL test database. The test bootstrap rejects non-MySQL connections and database names without a standalone `test` or `testing` segment. Configure `.env.testing` using a dedicated database and user; never point tests at the development database. See [Testing Database](docs/TESTING_DATABASE.md).

```sh
php artisan test
npm run build
```

There is no JavaScript test script in `package.json`. Browser verification is separate from the production build and should only be reported when performed.

## Documentation

- [API endpoints and filters](docs/API_DOCUMENTATION.md)
- [Authentication and security](docs/AUTHENTICATION_SECURITY.md)
- [Database schema and relationships](docs/DATABASE_DESIGN.md)
- [Development roadmap](docs/DEVELOPMENT_ROADMAP.md)
- [Registration OTP lifecycle](docs/REGISTRATION_OTP.md)
- [Local mail and OTP testing](docs/LOCAL_MAIL_TESTING.md)

The project declares the MIT license in `composer.json`.
