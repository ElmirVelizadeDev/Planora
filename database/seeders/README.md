# Local User Task Demo Data

`UserTasksDemoSeeder` creates or refreshes five clearly marked task records for each existing, active, non-deleted user account (`role=user`). It uses only the current `tasks` columns and assigns ownership and creator to that user. The fixed per-user task keys make repeated runs idempotent; existing unmarked tasks are not changed or deleted. A matching soft-deleted demo task is restored.

`DatabaseSeeder` calls this seeder only when `APP_ENV=local`. It also calls `ProjectDemoSeeder` in that environment. This seeder itself refuses to run unless `APP_ENV=local`.

From the project root, after checking that `.env` targets the intended local database, run only:

```sh
php artisan db:seed --class="Database\Seeders\UserTasksDemoSeeder"
```

Seed rows are identifiable by the `[Planora Seed U{user-id} T{01-05}]` title prefix and the development seed note in their description. These records are standalone (`project_id` is null). To verify counts per user, count rows with `owner_id` set to each eligible user's ID and a title beginning with `[Planora Seed U{user-id} T`.
