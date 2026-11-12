# Isolated MySQL Test Database

PHPUnit feature tests use MySQL. The test bootstrap refuses to start unless the active driver is `mysql` and the database name contains a standalone `test` or `testing` segment (at the beginning/end or separated by `_` or `-`). This prevents accidentally running the suite against the normal development database. Do not disable or bypass the check.

1. Create a dedicated test database and a test database user whose permissions are limited to that database.
2. Copy `.env.testing.example` to `.env.testing`, configure the dedicated connection and test-only credentials, and generate a separate application key with `php artisan key:generate --env=testing`. Choose an available MySQL port; do not copy development credentials.
3. Keep the test database distinct from the development database even when they share a MySQL server. Do not change PHPUnit to use in-memory SQLite; the suite relies on MySQL migrations, indexes, constraints, and locking behavior.
4. Run `php artisan test` only after confirming `.env.testing` selects the isolated database.

Test setup and teardown may migrate or roll back tables in that configured test database. Never point PHPUnit at the development database, and do not use `migrate:fresh` against a database containing data.
