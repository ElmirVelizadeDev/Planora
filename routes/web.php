<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;

$adminEntryPath = config('planora.admin_entry_path');
if ((! is_string($adminEntryPath) || $adminEntryPath === '') && app()->environment('production')) {
    throw new RuntimeException('PLANORA_ADMIN_ENTRY_PATH must be configured in production.');
}

if (is_string($adminEntryPath) && $adminEntryPath !== '') {
    if (strlen($adminEntryPath) < 48 || preg_match('/\A[A-Za-z0-9_-]+\z/', $adminEntryPath) !== 1) {
        throw new RuntimeException('PLANORA_ADMIN_ENTRY_PATH must be at least 48 URL-safe characters.');
    }

    Route::get('/'.$adminEntryPath, [AuthController::class, 'adminLoginPage'])->name('admin.secret-entry');
    Route::post('/'.$adminEntryPath, [AuthController::class, 'adminLogin'])
        ->middleware('throttle:auth-login')
        ->name('admin.secret-login');
}


Route::view('/', 'welcome')->middleware('maintenance.scope:public_website');

Route::view('/login', 'welcome')->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication'])->name('login');
Route::view('/register', 'welcome')->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication']);
Route::view('/terms-and-conditions', 'welcome')->middleware('maintenance.scope:public_website')->name('terms-and-conditions');
Route::view('/privacy-policy', 'welcome')->middleware('maintenance.scope:public_website')->name('privacy-policy');
Route::view('/forgot-password', 'welcome')->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication']);
Route::view('/reset-password', 'welcome')->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication']);
Route::view('/verify-email', 'welcome')->middleware(['maintenance.scope:public_website', 'maintenance.scope:authentication']);
Route::view('/401', 'welcome');
Route::view('/403', 'welcome');
Route::view('/404', 'welcome');
Route::view('/419', 'welcome');
Route::view('/429', 'welcome');
Route::view('/500', 'welcome');
Route::view('/503', 'welcome');

Route::view('/profile/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:user,moderator,admin']);

Route::view('/account/settings', 'welcome')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:user,moderator,admin']);

Route::view('/admin/users/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access']);

Route::view('/admin/activity-logs/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'can:viewActivity,App\\Models\\User']);

Route::view('/admin/categories/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access:categories.view']);

Route::view('/admin/projects/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access:projects.view']);

Route::view('/admin/tasks/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access:tasks.view']);

Route::view('/admin/reports/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access:reports.view']);

Route::view('/admin/dashboard', 'welcome')
    ->middleware(['maintenance.bootstrap', 'maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin,moderator', 'dashboard.access']);

Route::view('/admin/system-maintenance', 'welcome')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin']);

Route::view('/admin/{path?}', 'welcome')
    ->where('path', '^(?!users(?:/|$)).*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:admin']);

Route::view('/{path}', 'welcome')
    ->where('path', '^(?!api(?:/|$)|sanctum(?:/|$)|admin(?:/|$)).*')
    ->middleware(['maintenance.scope:dashboard_workspace', 'auth', 'active', 'auth.session', 'role:user,moderator']);
