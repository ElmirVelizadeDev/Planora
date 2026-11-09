# Isolated MySQL test database

Feature tests require MySQL. Do not point `APP_ENV=testing` at the development database. The test bootstrap refuses to run unless the configured driver is MySQL and the database name contains `test` or `testing`.

1. Create a separate MySQL database named `planora_security_test` and a dedicated test user with privileges limited to that database.
2. Copy `.env.testing.example` to `.env.testing`, then set its test-only credentials and generate a separate key with `php artisan key:generate --env=testing`.
3. Keep the MySQL server and PHP test process on the same machine or an explicitly isolated test network. SQLite is intentionally not substituted because these tests exercise MySQL locking, indexes, constraints, and migrations.
4. Run the full suite with `php artisan test`.

Test migrations may create and roll back tables only in the explicitly named test database. Never run `migrate:fresh` against the development database.
