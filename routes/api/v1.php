<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\Categories\CategoryController;
use App\Http\Controllers\Api\V1\Admin\Users\UserActivityController;
use App\Http\Controllers\Api\V1\Admin\Projects\ProjectManagementController;
use App\Http\Controllers\Api\V1\Admin\Users\UserManagementController;
use App\Http\Controllers\Api\V1\Admin\Users\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\Users\UserRolePermissionsController;
use App\Http\Controllers\Api\V1\User\Profile\CurrentUserController;
use App\Http\Controllers\Api\V1\User\Profile\CurrentUserProfileController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Admin\AdminMfaController;
use App\Http\Controllers\Api\V1\Admin\Tasks\TaskController;
use App\Http\Controllers\Api\V1\User\Tasks\UserTaskController;
use App\Http\Controllers\Api\V1\User\Projects\UserProjectController;
use App\Http\Controllers\Api\V1\User\Categories\UserCategoryController;
use App\Http\Controllers\Api\V1\User\Reports\UserTaskReportController;
use App\Http\Controllers\Api\V1\Admin\Projects\ProjectTaskManagementController;
use App\Http\Controllers\Api\V1\Admin\Reports\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogs\ActivityLogsController;
use App\Http\Controllers\Api\V1\Admin\Settings\AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\SystemMaintenance\SystemMaintenanceController;
use App\Http\Controllers\Api\V1\Settings\SettingsBrandingController;
use App\Http\Controllers\Api\V1\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\V1\MaintenanceStatusController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Admin\Backups\BackupController;
use App\Http\Controllers\Api\V1\Search\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/settings/branding', [SettingsBrandingController::class, 'show'])->name('settings.branding');
Route::get('/maintenance/status', [MaintenanceStatusController::class, 'show'])->name('maintenance.status');

Route::prefix('auth')->name('auth.')
    ->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login');
    Route::post('/admin/mfa/verify-login', [AuthController::class, 'verifyAdminMfa'])->middleware('throttle:admin-mfa-login')->name('admin-mfa.verify-login');
    Route::post('/admin/mfa/enroll-login', [AdminMfaController::class, 'beginLoginEnrollment'])->middleware('throttle:admin-mfa-enrollment')->name('admin-mfa.enroll-login');
    Route::post('/admin/mfa/enroll-login/verify', [AdminMfaController::class, 'verifyLoginEnrollment'])->middleware('throttle:admin-mfa-enrollment')->name('admin-mfa.enroll-login.verify');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
    Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:10,1')->name('register.verify');
    Route::post('/register/resend', [AuthController::class, 'resendRegistrationCode'])->middleware('throttle:3,1')->name('register.resend');
    Route::post('/password/forgot', [PasswordController::class, 'requestCode'])->middleware('throttle:5,1')->name('password.forgot');
    Route::post('/password/resend', [PasswordController::class, 'resendCode'])->middleware('throttle:3,1')->name('password.resend');
    Route::post('/password/verify', [PasswordController::class, 'verifyCode'])->middleware('throttle:10,1')->name('password.verify');
    Route::post('/password/reset', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.reset');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

Route::middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active'])->group(function () {
    Route::get('/search', GlobalSearchController::class)->middleware('role:user,admin,moderator')->name('search.index');
    Route::get('/user', [CurrentUserController::class, 'show'])->middleware('maintenance.bootstrap')->name('user');
    Route::get('/user/overview', [CurrentUserController::class, 'overview'])->name('user.overview');
    Route::patch('/user/profile', [CurrentUserProfileController::class, 'update'])->name('user.profile.update');
    Route::post('/user/avatar', [CurrentUserProfileController::class, 'updateAvatar'])->name('user.avatar.update');
    Route::delete('/user/avatar', [CurrentUserProfileController::class, 'removeAvatar'])->name('user.avatar.remove');
    Route::put('/user/password', [CurrentUserProfileController::class, 'changePassword'])
        ->middleware('throttle:6,1')->name('user.password.update');
});

Route::prefix('admin/mfa')->name('admin.mfa.')
    ->middleware(['maintenance.bootstrap', 'maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::get('/', [AdminMfaController::class, 'status'])->name('status');
        Route::post('/enrollment', [AdminMfaController::class, 'begin'])->middleware('throttle:admin-mfa-enrollment')->name('enrollment.start');
        Route::post('/enrollment/verify', [AdminMfaController::class, 'enable'])->middleware('throttle:admin-mfa-enrollment')->name('enrollment.verify');
        Route::delete('/enrollment', [AdminMfaController::class, 'cancelEnrollment'])->name('enrollment.cancel');
        Route::post('/disable', [AdminMfaController::class, 'disable'])->middleware('step-up:mfa.disable')->name('disable');
        Route::post('/recovery-codes', [AdminMfaController::class, 'regenerateRecoveryCodes'])->middleware(['step-up:mfa.recovery', 'throttle:5,1'])->name('recovery.regenerate');
    });

Route::post('/admin/step-up/verify', [AdminMfaController::class, 'verifyStepUp'])
    ->middleware(['maintenance.bootstrap', 'maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin', 'throttle:admin-mfa-step-up'])
    ->name('admin.step-up.verify');

Route::prefix('notifications')->name('notifications.')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:user,admin'])->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::put('/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification')->name('read');
    });

Route::prefix('admin/backups')->name('admin.backups.')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->middleware('throttle:3,1')->name('store');
        Route::get('/{backup}', [BackupController::class, 'show'])->where('backup', '[0-9a-fA-F-]{36}')->name('show');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->where('backup', '[0-9a-fA-F-]{36}')->middleware('throttle:10,1')->name('download');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->where('backup', '[0-9a-fA-F-]{36}')->middleware(['throttle:3,1', 'step-up:backups.delete'])->name('destroy');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->where('backup', '[0-9a-fA-F-]{36}')->middleware(['throttle:1,5', 'step-up:backups.restore'])->name('restore');
    });

Route::prefix('admin/system-maintenance')->name('admin.system-maintenance.')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::get('/', [SystemMaintenanceController::class, 'show'])->name('show');
        Route::patch('/preferences', [SystemMaintenanceController::class, 'updatePreferences'])->middleware('step-up:system.maintenance')->name('preferences.update');
        Route::patch('/scopes/{scope}', [SystemMaintenanceController::class, 'updateScope'])
            ->whereIn('scope', ['public-website', 'authentication', 'dashboard-workspace'])->middleware('step-up:system.maintenance')->name('scopes.update');
        Route::post('/enable', [SystemMaintenanceController::class, 'enable'])->middleware(['throttle:6,1', 'step-up:system.maintenance'])->name('enable');
        Route::post('/disable', [SystemMaintenanceController::class, 'disable'])->middleware(['throttle:6,1', 'step-up:system.maintenance'])->name('disable');
    });

Route::get('/admin/dashboard', [AdminDashboardController::class, 'show'])
    ->middleware(['maintenance.bootstrap', 'maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->name('admin.dashboard');

Route::prefix('admin/roles-permissions')->name('admin.roles-permissions.')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::get('/', [UserRolePermissionsController::class, 'index'])->name('index');
        Route::get('/moderators', [UserRolePermissionsController::class, 'moderators'])->name('moderators.index');
        Route::get('/moderators/{user}', [UserRolePermissionsController::class, 'showModerator'])->name('moderators.show');
        Route::put('/moderators/{user}', [UserRoleController::class, 'updatePermissions'])->middleware('step-up:roles.manage')->name('moderators.permissions.update');
    });

Route::prefix('admin/users')->name('admin.users.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->middleware('can:viewAny,App\\Models\\User')->name('index');
    Route::get('/activity', [UserActivityController::class, 'index'])->middleware('can:viewActivity,App\\Models\\User')->name('activity.index');
    Route::get('/roles-permissions', [UserRolePermissionsController::class, 'index'])->middleware('can:viewAny,App\\Models\\User')->name('roles.permissions');
    Route::post('/', [UserManagementController::class, 'store'])->middleware(['role:admin', 'step-up:roles.manage'])->name('store');
    Route::post('/{user}/password', [UserManagementController::class, 'changePassword'])->middleware(['role:admin', 'step-up:users.manage'])->name('password.change');
    Route::get('/{user}', [UserManagementController::class, 'show'])->middleware('can:view,user')->name('show');
    Route::patch('/{user}', [UserManagementController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->middleware(['role:admin', 'step-up:users.manage'])->name('destroy');
    Route::post('/{userId}/restore', [UserManagementController::class, 'restore'])->middleware(['role:admin', 'step-up:users.manage'])->name('restore');
    Route::patch('/{user}/role', [UserRoleController::class, 'updateRole'])->middleware(['role:admin', 'step-up:roles.manage'])->name('role.update');
    Route::patch('/{user}/status', [UserRoleController::class, 'updateStatus'])->middleware('step-up:users.manage')->name('status.update');
    Route::put('/{user}/permissions', [UserRoleController::class, 'updatePermissions'])->middleware(['role:admin', 'step-up:roles.manage'])->name('permissions.update');
});

Route::prefix('admin/tasks')->name('admin.tasks.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [TaskController::class, 'index'])->middleware(['management.access:tasks.view', 'can:viewAny,App\\Models\\Task'])->name('index');
    Route::get('/assignees', [TaskController::class, 'assignees'])->middleware('role:admin')->name('assignees');
    Route::post('/', [TaskController::class, 'store'])->middleware('role:admin')->name('store');
    Route::post('/{taskId}/restore', [TaskController::class, 'restore'])->whereNumber('taskId')->middleware(['role:admin', 'step-up:users.manage'])->name('restore');
    Route::get('/{task}', [TaskController::class, 'show'])->middleware('can:view,task')->name('show');
    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->middleware(['management.access:tasks.status.manage', 'can:manageStatus,task'])->name('status.update');
    Route::patch('/{task}', [TaskController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->middleware(['role:admin', 'step-up:users.manage'])->name('destroy');
});

Route::prefix('admin/projects')->name('admin.projects.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [ProjectManagementController::class, 'index'])->middleware(['management.access:projects.view', 'can:viewAny,App\\Models\\Project'])->name('index');
    Route::get('/owners', [ProjectManagementController::class, 'owners'])->middleware('role:admin')->name('owners');
    Route::get('/{project}/tasks', [ProjectTaskManagementController::class, 'index'])->whereNumber('project')->middleware(['management.access:projects.view', 'can:view,project'])->name('tasks.index');
    Route::post('/{project}/tasks', [ProjectTaskManagementController::class, 'store'])->whereNumber('project')->middleware('role:admin')->name('tasks.store');
    Route::get('/{project}', [ProjectManagementController::class, 'show'])->whereNumber('project')->middleware('can:view,project')->name('show');
    Route::post('/', [ProjectManagementController::class, 'store'])->middleware('role:admin')->name('store');
    Route::patch('/{project}/status', [ProjectManagementController::class, 'updateStatus'])->middleware(['management.access:projects.status.manage', 'can:manageStatus,project'])->name('status.update');
    Route::patch('/{project}', [ProjectManagementController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{project}', [ProjectManagementController::class, 'destroy'])->middleware(['role:admin', 'step-up:users.manage'])->name('destroy');
});

Route::prefix('admin/categories')->name('admin.categories.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->middleware(['management.access:categories.view', 'can:viewAny,App\\Models\\Category'])->name('index');
    Route::post('/', [CategoryController::class, 'store'])->name('store');
    Route::post('/{category}/restore', [CategoryController::class, 'restore'])->whereNumber('category')->middleware(['role:admin', 'step-up:users.manage'])->name('restore');
    Route::get('/{category}', [CategoryController::class, 'show'])->whereNumber('category')->middleware('management.access:categories.view')->name('show');
    Route::patch('/{category}', [CategoryController::class, 'update'])->whereNumber('category')->middleware('role:admin')->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->middleware(['role:admin', 'step-up:users.manage'])->name('destroy');
});

Route::prefix('admin/reports')->name('admin.reports.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [AdminReportController::class, 'overview'])->middleware('management.access:reports.view')->name('overview');
    Route::get('/filters', [AdminReportController::class, 'filterOptions'])->middleware('management.access:reports.view')->name('filters');
    Route::get('/tables/{table}', [AdminReportController::class, 'table'])
        ->where('table', 'registrations|projects|tasks|overdue|upcoming|categories|unused_categories|assignees')
        ->middleware('management.access:reports.view')->name('tables.show');
    Route::get('/export', [AdminReportController::class, 'export'])->middleware('role:admin')->name('export');
});

Route::prefix('admin/activity-logs')->name('admin.activity-logs.')->middleware([
    'maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin,moderator', 'can:viewActivity,App\\Models\\User',
])->group(function () {
    Route::get('/', [ActivityLogsController::class, 'index'])->name('index');
    Route::get('/filters', [ActivityLogsController::class, 'filters'])->name('filters');
    Route::get('/statistics', [ActivityLogsController::class, 'statistics'])->name('statistics');
    Route::get('/export', [ActivityLogsController::class, 'export'])->name('export');
    Route::get('/{activityLog}', [ActivityLogsController::class, 'show'])->whereNumber('activityLog')->name('show');
});

Route::prefix('admin/settings')->name('admin.settings.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:admin'])->group(function () {
    Route::get('/', [AdminSettingsController::class, 'index'])->name('index');
    Route::get('/{group}', [AdminSettingsController::class, 'show'])->whereIn('group', ['general', 'security', 'email'])->name('show');
    Route::put('/{group}', [AdminSettingsController::class, 'update'])->whereIn('group', ['general', 'security', 'email'])->middleware('step-up:settings.security')->name('update');
    Route::post('/{group}/reset', [AdminSettingsController::class, 'reset'])->whereIn('group', ['general', 'security', 'email'])->middleware('step-up:settings.security')->name('reset');
    Route::post('/email/test', [AdminSettingsController::class, 'testEmail'])->middleware('throttle:3,1')->name('email.test');
    Route::post('/general/logo', [AdminSettingsController::class, 'uploadLogo'])->name('general.logo.upload');
    Route::delete('/general/logo', [AdminSettingsController::class, 'removeLogo'])->name('general.logo.remove');
});

Route::prefix('projects')->name('projects.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserProjectController::class, 'index'])->name('index');
    Route::get('/{project}/tasks', [UserProjectController::class, 'tasks'])->whereNumber('project')->name('tasks.index');
    Route::get('/{project}', [UserProjectController::class, 'show'])->whereNumber('project')->name('show');
});

Route::prefix('categories')->name('categories.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserCategoryController::class, 'index'])->name('index');
    Route::get('/{category}', [UserCategoryController::class, 'show'])->whereNumber('category')->name('show');
});

Route::prefix('tasks')->name('tasks.')->middleware(['maintenance.scope:dashboard_workspace', 'auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/reports', UserTaskReportController::class)->name('reports');
    Route::get('/calendar', [UserTaskController::class, 'calendar'])->name('calendar');
    Route::get('/', [UserTaskController::class, 'index'])->name('index');
    Route::post('/', [UserTaskController::class, 'store'])->name('store');
    Route::get('/{task}', [UserTaskController::class, 'show'])->whereNumber('task')->name('show');
    Route::patch('/{task}', [UserTaskController::class, 'update'])->whereNumber('task')->name('update');
    Route::patch('/{task}/status', [UserTaskController::class, 'updateStatus'])->whereNumber('task')->name('status.update');
    Route::delete('/{task}', [UserTaskController::class, 'destroy'])->whereNumber('task')->name('destroy');
});
