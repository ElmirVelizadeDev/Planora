# Local Demo Seeders

## Important environment warning

`DatabaseSeeder` invokes `PlanoraUsersSeeder` before checking the environment. That account seeder is not environment-guarded and uses a built-in development credential for newly created fixture accounts. Do not run `php artisan db:seed` or `php artisan migrate --seed` in production or against a database containing non-disposable data. Use these instructions only after confirming `.env` targets an isolated local development database.

## Category catalog

`CategorySeeder` is local-only and adds these ten categories when no case-insensitive active or archived name already exists: Web Development, UI/UX Design, Mobile Development, Database, Marketing, Research, Bug Fixes, Documentation, DevOps, and Content Creation. Each includes a description and valid hex color. Existing Category rows are not updated, archived names prevent duplicate insertion, and repeat runs are idempotent.

Run only this seeder after verifying the local database target:

```sh
php artisan db:seed --class="Database\Seeders\CategorySeeder"
```

## Project and Task demo records

`ProjectDemoSeeder` and `UserTasksDemoSeeder` are also local-only and refuse to run outside `APP_ENV=local`. `DatabaseSeeder` invokes Category, Project, and User Task seeders only in that environment.

- `ProjectDemoSeeder` creates or refreshes four marked Projects per active, non-deleted normal User. Each Project has four purpose-matched Tasks, for sixteen linked Tasks per User. It refreshes only records with its Planora seed markers and restores matching soft-deleted demo Tasks.
- `UserTasksDemoSeeder` creates or refreshes five marked standalone Tasks per active, non-deleted normal User. Matching soft-deleted demo Tasks are restored.
- Both seeders preserve existing non-null `category_id` values, including associations to archived Categories. A matching active Category is assigned only when a demo record is new or has no Category association. They do not modify unrelated records.
- Project and Task ownership/assignment fields are set for the local demo User so the seeded records follow normal visibility rules.

Run them individually only in the verified local database:

```sh
php artisan db:seed --class="Database\Seeders\ProjectDemoSeeder"
php artisan db:seed --class="Database\Seeders\UserTasksDemoSeeder"
```

The Category, Project, and User Task seeder guards do not make `DatabaseSeeder` safe outside local development because `PlanoraUsersSeeder` is currently called unconditionally. Review the implementation before using any seeder against a database with existing data.
