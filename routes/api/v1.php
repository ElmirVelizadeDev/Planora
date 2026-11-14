<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\Categories\CategoryController;
use App\Http\Controllers\Api\V1\Admin\Users\UserActivityController;
use App\Http\Controllers\Api\V1\Admin\Projects\ProjectManagementController;
use App\Http\Controllers\Api\V1\Admin\Users\UserManagementController;
use App\Http\Controllers\Api\V1\Admin\Users\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\Users\UserRolePermissionsController;
use App\Http\Controllers\Api\V1\User\Profile\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Admin\Tasks\TaskController;
use App\Http\Controllers\Api\V1\User\Tasks\UserTaskController;
use App\Http\Controllers\Api\V1\User\Projects\UserProjectController;
use App\Http\Controllers\Api\V1\User\Categories\UserCategoryController;
use App\Http\Controllers\Api\V1\Admin\Projects\ProjectTaskManagementController;
use App\Http\Controllers\Api\V1\Admin\Reports\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogs\ActivityLogsController;
use App\Http\Controllers\Api\V1\Admin\Settings\AdminSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsBrandingController;
use App\Http\Controllers\Api\V1\Dashboard\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/settings/branding', [SettingsBrandingController::class, 'show'])->name('settings.branding');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login');
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

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', [CurrentUserController::class, 'show'])->name('user');
});

Route::get('/admin/dashboard', [AdminDashboardController::class, 'show'])
    ->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->name('admin.dashboard');

Route::prefix('admin/roles-permissions')->name('admin.roles-permissions.')
    ->middleware(['auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::get('/', [UserRolePermissionsController::class, 'index'])->name('index');
        Route::get('/moderators', [UserRolePermissionsController::class, 'moderators'])->name('moderators.index');
        Route::get('/moderators/{user}', [UserRolePermissionsController::class, 'showModerator'])->name('moderators.show');
        Route::put('/moderators/{user}', [UserRoleController::class, 'updatePermissions'])->name('moderators.permissions.update');
    });

Route::prefix('admin/users')->name('admin.users.')->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->middleware('can:viewAny,App\\Models\\User')->name('index');
    Route::get('/activity', [UserActivityController::class, 'index'])->middleware('can:viewActivity,App\\Models\\User')->name('activity.index');
    Route::get('/roles-permissions', [UserRolePermissionsController::class, 'index'])->middleware('can:viewAny,App\\Models\\User')->name('roles.permissions');
    Route::post('/', [UserManagementController::class, 'store'])->middleware('role:admin')->name('store');
    Route::post('/{user}/password', [UserManagementController::class, 'changePassword'])->middleware('role:admin')->name('password.change');
    Route::get('/{user}', [UserManagementController::class, 'show'])->middleware('can:view,user')->name('show');
    Route::patch('/{user}', [UserManagementController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->middleware('role:admin')->name('destroy');
    Route::post('/{userId}/restore', [UserManagementController::class, 'restore'])->name('restore');
    Route::patch('/{user}/role', [UserRoleController::class, 'updateRole'])->middleware('role:admin')->name('role.update');
    Route::patch('/{user}/status', [UserRoleController::class, 'updateStatus'])->name('status.update');
    Route::put('/{user}/permissions', [UserRoleController::class, 'updatePermissions'])->middleware('role:admin')->name('permissions.update');
});

Route::prefix('admin/tasks')->name('admin.tasks.')->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [TaskController::class, 'index'])->middleware(['management.access:tasks.view', 'can:viewAny,App\\Models\\Task'])->name('index');
    Route::get('/assignees', [TaskController::class, 'assignees'])->middleware('role:admin')->name('assignees');
    Route::post('/', [TaskController::class, 'store'])->middleware('role:admin')->name('store');
    Route::post('/{taskId}/restore', [TaskController::class, 'restore'])->whereNumber('taskId')->middleware('role:admin')->name('restore');
    Route::get('/{task}', [TaskController::class, 'show'])->middleware('can:view,task')->name('show');
    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->middleware(['management.access:tasks.status.manage', 'can:manageStatus,task'])->name('status.update');
    Route::patch('/{task}', [TaskController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->middleware('role:admin')->name('destroy');
});

Route::prefix('admin/projects')->name('admin.projects.')->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [ProjectManagementController::class, 'index'])->middleware(['management.access:projects.view', 'can:viewAny,App\\Models\\Project'])->name('index');
    Route::get('/owners', [ProjectManagementController::class, 'owners'])->middleware('role:admin')->name('owners');
    Route::get('/{project}/tasks', [ProjectTaskManagementController::class, 'index'])->whereNumber('project')->middleware(['management.access:projects.view', 'can:view,project'])->name('tasks.index');
    Route::post('/{project}/tasks', [ProjectTaskManagementController::class, 'store'])->whereNumber('project')->middleware('role:admin')->name('tasks.store');
    Route::get('/{project}', [ProjectManagementController::class, 'show'])->whereNumber('project')->middleware('can:view,project')->name('show');
    Route::post('/', [ProjectManagementController::class, 'store'])->middleware('role:admin')->name('store');
    Route::patch('/{project}/status', [ProjectManagementController::class, 'updateStatus'])->middleware(['management.access:projects.status.manage', 'can:manageStatus,project'])->name('status.update');
    Route::patch('/{project}', [ProjectManagementController::class, 'update'])->middleware('role:admin')->name('update');
    Route::delete('/{project}', [ProjectManagementController::class, 'destroy'])->middleware('role:admin')->name('destroy');
});

Route::prefix('admin/categories')->name('admin.categories.')->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->middleware(['management.access:categories.view', 'can:viewAny,App\\Models\\Category'])->name('index');
    Route::post('/', [CategoryController::class, 'store'])->name('store');
    Route::post('/{category}/restore', [CategoryController::class, 'restore'])->whereNumber('category')->middleware('role:admin')->name('restore');
    Route::get('/{category}', [CategoryController::class, 'show'])->whereNumber('category')->middleware('management.access:categories.view')->name('show');
    Route::patch('/{category}', [CategoryController::class, 'update'])->whereNumber('category')->middleware('role:admin')->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->middleware('role:admin')->name('destroy');
});

Route::prefix('admin/reports')->name('admin.reports.')->middleware(['auth:sanctum', 'active', 'role:admin,moderator'])->group(function () {
    Route::get('/', [AdminReportController::class, 'overview'])->middleware('management.access:reports.view')->name('overview');
    Route::get('/filters', [AdminReportController::class, 'filterOptions'])->middleware('management.access:reports.view')->name('filters');
    Route::get('/tables/{table}', [AdminReportController::class, 'table'])
        ->where('table', 'registrations|projects|tasks|overdue|upcoming|categories|unused_categories|assignees')
        ->middleware('management.access:reports.view')->name('tables.show');
    Route::get('/export', [AdminReportController::class, 'export'])->middleware('role:admin')->name('export');
});

Route::prefix('admin/activity-logs')->name('admin.activity-logs.')->middleware([
    'auth:sanctum', 'active', 'role:admin,moderator', 'can:viewActivity,App\\Models\\User',
])->group(function () {
    Route::get('/', [ActivityLogsController::class, 'index'])->name('index');
    Route::get('/filters', [ActivityLogsController::class, 'filters'])->name('filters');
    Route::get('/statistics', [ActivityLogsController::class, 'statistics'])->name('statistics');
    Route::get('/export', [ActivityLogsController::class, 'export'])->name('export');
    Route::get('/{activityLog}', [ActivityLogsController::class, 'show'])->whereNumber('activityLog')->name('show');
});

Route::prefix('admin/settings')->name('admin.settings.')->middleware(['auth:sanctum', 'active', 'role:admin'])->group(function () {
    Route::get('/', [AdminSettingsController::class, 'index'])->name('index');
    Route::get('/{group}', [AdminSettingsController::class, 'show'])->whereIn('group', ['general', 'security', 'email'])->name('show');
    Route::put('/{group}', [AdminSettingsController::class, 'update'])->whereIn('group', ['general', 'security', 'email'])->name('update');
    Route::post('/{group}/reset', [AdminSettingsController::class, 'reset'])->whereIn('group', ['general', 'security', 'email'])->name('reset');
    Route::post('/email/test', [AdminSettingsController::class, 'testEmail'])->middleware('throttle:3,1')->name('email.test');
    Route::post('/general/logo', [AdminSettingsController::class, 'uploadLogo'])->name('general.logo.upload');
    Route::delete('/general/logo', [AdminSettingsController::class, 'removeLogo'])->name('general.logo.remove');
});

Route::prefix('projects')->name('projects.')->middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserProjectController::class, 'index'])->name('index');
    Route::get('/{project}/tasks', [UserProjectController::class, 'tasks'])->whereNumber('project')->name('tasks.index');
    Route::get('/{project}', [UserProjectController::class, 'show'])->whereNumber('project')->name('show');
});

Route::prefix('categories')->name('categories.')->middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserCategoryController::class, 'index'])->name('index');
    Route::get('/{category}', [UserCategoryController::class, 'show'])->whereNumber('category')->name('show');
});

Route::prefix('tasks')->name('tasks.')->middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserTaskController::class, 'index'])->name('index');
    Route::post('/', [UserTaskController::class, 'store'])->name('store');
    Route::get('/{task}', [UserTaskController::class, 'show'])->whereNumber('task')->name('show');
    Route::patch('/{task}', [UserTaskController::class, 'update'])->whereNumber('task')->name('update');
    Route::patch('/{task}/status', [UserTaskController::class, 'updateStatus'])->whereNumber('task')->name('status.update');
    Route::delete('/{task}', [UserTaskController::class, 'destroy'])->whereNumber('task')->name('destroy');
});
