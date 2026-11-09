<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Admin\UserActivityController;
use App\Http\Controllers\Api\V1\Admin\UserManagementController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\UserRolePermissionsController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\PasswordController;
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
