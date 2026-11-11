<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Admin\UserActivityController;
use App\Http\Controllers\Api\V1\Admin\ProjectManagementController;
use App\Http\Controllers\Api\V1\Admin\UserManagementController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\UserRolePermissionsController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserTaskController;
use App\Http\Controllers\Api\V1\UserProjectController;
use App\Http\Controllers\Api\V1\Admin\ProjectTaskManagementController;
use Illuminate\Support\Facades\Route;

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

Route::prefix('admin/tasks')->name('admin.tasks.')->middleware(['auth:sanctum', 'active', 'role:admin'])->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::get('/assignees', [TaskController::class, 'assignees'])->name('assignees');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::get('/{task}', [TaskController::class, 'show'])->name('show');
    Route::patch('/{task}', [TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
});

Route::prefix('admin/projects')->name('admin.projects.')->middleware(['auth:sanctum', 'active', 'role:admin'])->group(function () {
    Route::get('/', [ProjectManagementController::class, 'index'])->name('index');
    Route::get('/owners', [ProjectManagementController::class, 'owners'])->name('owners');
    Route::get('/{project}/tasks', [ProjectTaskManagementController::class, 'index'])->whereNumber('project')->name('tasks.index');
    Route::post('/{project}/tasks', [ProjectTaskManagementController::class, 'store'])->whereNumber('project')->name('tasks.store');
    Route::get('/{project}', [ProjectManagementController::class, 'show'])->whereNumber('project')->name('show');
    Route::post('/', [ProjectManagementController::class, 'store'])->name('store');
    Route::patch('/{project}', [ProjectManagementController::class, 'update'])->name('update');
    Route::delete('/{project}', [ProjectManagementController::class, 'destroy'])->name('destroy');
});

Route::prefix('projects')->name('projects.')->middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserProjectController::class, 'index'])->name('index');
    Route::get('/{project}/tasks', [UserProjectController::class, 'tasks'])->whereNumber('project')->name('tasks.index');
    Route::get('/{project}', [UserProjectController::class, 'show'])->whereNumber('project')->name('show');
});

Route::prefix('tasks')->name('tasks.')->middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
    Route::get('/', [UserTaskController::class, 'index'])->name('index');
    Route::post('/', [UserTaskController::class, 'store'])->name('store');
    Route::get('/{task}', [UserTaskController::class, 'show'])->whereNumber('task')->name('show');
    Route::patch('/{task}', [UserTaskController::class, 'update'])->whereNumber('task')->name('update');
    Route::patch('/{task}/status', [UserTaskController::class, 'updateStatus'])->whereNumber('task')->name('status.update');
    Route::delete('/{task}', [UserTaskController::class, 'destroy'])->whereNumber('task')->name('destroy');
});
