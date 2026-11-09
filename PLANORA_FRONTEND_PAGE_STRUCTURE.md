# Planora Frontend Page Structure

Structural inventory as of 2026-11-05. Page and component files are scaffolds only; behavior and visual design have not been implemented.

## User Panel

Required inventory: 30 pages.

- Dashboard: DashboardIndex — existing resources/js/pages/app/dashboard/Index.vue.
- Tasks: TasksIndex, TasksCreate, TasksEdit, TasksShow — existing Index.vue, Create.vue, Edit.vue, Show.vue under resources/js/pages/app/tasks/.
- Projects: ProjectsIndex, ProjectsCreate, ProjectsEdit, ProjectsShow — newly created under resources/js/pages/app/projects/.
- Calendar: CalendarIndex — existing resources/js/pages/app/calendar/Index.vue.
- Categories: CategoriesIndex, CategoriesCreate, CategoriesEdit — existing under resources/js/pages/app/categories/; CategoriesShow newly created.
- Tags: TagsIndex, TagsCreate, TagsEdit — newly created under resources/js/pages/app/tags/.
- Subtasks: SubtasksCreate, SubtasksEdit — newly created under resources/js/pages/app/subtasks/; no standalone list page.
- Notifications: NotificationsIndex, NotificationsShow — newly created under resources/js/pages/app/notifications/.
- Profile: ProfileIndex maps to existing Show.vue; ProfileEdit maps to existing Edit.vue under resources/js/pages/app/profile/.
- Settings: SettingsIndex — existing resources/js/pages/app/settings/Index.vue; SettingsAccount, SettingsAppearance, SettingsNotifications, SettingsSecurity newly created there.
- Productivity & Reports: ProductivityIndex, ProductivityReports newly created under resources/js/pages/app/productivity/. Existing Statistics Index.vue and chart components are retained without duplication.

Each new page has a corresponding CSS file in resources/css/pages/<area>/styles/. Existing pages retain individual CSS files under resources/css/pages/app/<area>/.

## Admin Panel

All 30 required pages are newly scaffolded under resources/js/pages/admin/, with individual CSS files under matching resources/css/pages/admin/<area>/styles/:

- Dashboard: AdminDashboard
- Users: UsersIndex, UsersShow, UsersEdit, UsersCreate
- Roles & Permissions: RolesIndex, RolesCreate, RolesEdit, RolesShow, PermissionsIndex
- Tasks: AdminTasksIndex, AdminTasksShow
- Projects: AdminProjectsIndex, AdminProjectsShow
- Categories: AdminCategoriesIndex, AdminCategoriesCreate, AdminCategoriesEdit
- Tags: AdminTagsIndex, AdminTagsCreate, AdminTagsEdit
- Reports & Analytics: AdminReportsIndex, AdminAnalyticsIndex
- Notifications: AdminNotificationsIndex, AdminNotificationsCreate
- Activity Logs: ActivityLogsIndex, ActivityLogsShow
- System Settings: AdminSettingsIndex, AdminSettingsGeneral, AdminSettingsSecurity, AdminSettingsEmail

Task oversight, account restrictions, and authorization policy remain implementation decisions; the scaffolds encode no access behavior.

## Authentication and Errors

Five authentication components already exist and are preserved: Login.vue, Register.vue, ForgotPassword.vue, ResetPassword.vue, VerifyEmail.vue under resources/js/pages/auth/, each with an existing individual stylesheet under resources/css/pages/auth/.

New error components and dedicated styles were added for Error403, Error404, Error419, Error429, Error500, Error503 under resources/js/pages/errors/ and resources/css/pages/errors/styles/.

## Shared Components

Existing under resources/js/shared/components/: AppButton (button), AppInput (input), AppModal, AppDropdown, AppPagination, AppConfirmDialog, and AppLoader; AppAlert is also present.

New minimal shared component locations: BaseSelect, BaseTextarea, BaseCheckbox, BaseBadge, BaseCard, BaseTable, BaseTooltip, BaseEmptyState, BaseSkeleton, Sidebar, Topbar, Breadcrumbs, PageHeader, SearchBar, FilterPanel, NotificationDropdown, UserAvatar. App-prefixed components serve as equivalents of BaseButton, BaseInput, BaseModal, BaseDropdown, BasePagination, BaseConfirmDialog, and BaseLoader; they were not duplicated.

## Layouts

- User: existing resources/js/layouts/DashboardLayout.vue serves as the User Panel layout equivalent and was retained under the established project name.
- Admin: new resources/js/layouts/AdminLayout.vue.
- Authentication: existing resources/js/layouts/AuthLayout.vue.

User and Admin layouts remain separate. Shared visual tokens exist in resources/css/variables.css. Vue Router now owns page navigation; the page components remain scaffolds without UI design.

## CSS Organization

Existing page styles remain in the established lowercase feature structure under resources/css/pages/app/ and resources/css/pages/auth/. Every new page component has one matching stylesheet in its area's styles/ folder, named after the page component. No page stylesheet is shared. Existing global styles remain in resources/css/.

## Existing and Newly Created Files

- Existing project files inspected: 212 before changes, including 13 existing User Panel page components, 5 authentication components, their page styles, shared components, layouts, and feature components.
- New files created: 128 (54 page components + 54 matching page styles, one Admin layout + its stylesheet, 17 shared component placeholders, and this document).
- Existing files preserved: all 212 pre-change files; no existing file was overwritten, renamed, or deleted.
- Existing User Panel page equivalents: 13 of 30. New User Panel pages: 17.
- New Admin Panel pages: 30.
- Authentication pages: 5 existing. Error pages: 6 new.
- New page styles: 54, plus one Admin layout stylesheet. Existing page styles are retained.

## Architectural Decisions Still Needed

- Whether to rename DashboardLayout.vue to UserLayout.vue; current project naming is DashboardLayout and it already fills the User layout role, so it was preserved.
- Route naming, registration, and authorization boundaries for Admin and User areas.
- Which task/project fields or account data administrators may inspect, especially where user privacy applies.
- Whether ProductivityIndex should ultimately replace or coexist with the existing Statistics page and route.
- Whether every Admin entity needs detail or edit screens beyond the requested inventory.

## Recommended Additions

- When implementation begins, connect pages to routes and intended layouts, with explicit role and privacy checks.
- Establish documented token and shared component usage conventions before visual implementation.

## User Tasks Management Implementation (2026-11-09)

- The existing User Tasks routes now use a database-backed `/api/v1/tasks` API scoped to tasks owned by or assigned to the authenticated user. Task details, create/edit, status updates, and soft deletion use the existing Task model and table.
- The Tasks Index provides database-backed task statistics, search, status/priority/due-date filters, sorting, and pagination. Completed and upcoming tasks are views of the same task list via filters; separate routes do not exist.
- `resources/js/features/tasks/user/components/UserTaskForm.vue` is shared by the existing Create and Edit routes. Task detail remains at the existing Show route.
- Category selection is omitted because the tasks schema has no category relationship/table. The task table is defined by `database/migrations/2026_11_09_000002_create_tasks_table.php` and includes ownership, assignment, creator, and soft-delete fields.
- The User Dashboard task overview now reads counts and recent tasks from the same authenticated task API, keeping it consistent with the Tasks pages.
- Local demo task records can be generated with `php artisan db:seed --class="Database\Seeders\UserTasksDemoSeeder"`; the standalone seeder is documented in `database/seeders/README.md` and is not included in `DatabaseSeeder`.
