<?php

use Illuminate\Support\Facades\Route;


Route::view('/', 'welcome');

Route::view('/login', 'welcome')->name('login');
Route::view('/register', 'welcome');
Route::view('/terms-and-conditions', 'welcome')->name('terms-and-conditions');
Route::view('/privacy-policy', 'welcome')->name('privacy-policy');
Route::view('/forgot-password', 'welcome');
Route::view('/reset-password', 'welcome');
Route::view('/verify-email', 'welcome');
Route::view('/403', 'welcome');
Route::view('/419', 'welcome');
Route::view('/429', 'welcome');
Route::view('/500', 'welcome');
Route::view('/503', 'welcome');

Route::view('/admin/users/{path?}', 'welcome')
    ->where('path', '.*')
    ->middleware(['auth', 'active', 'auth.session', 'role:admin,moderator', 'management.access']);

Route::view('/admin/{path?}', 'welcome')
    ->where('path', '^(?!users(?:/|$)).*')
    ->middleware(['auth', 'active', 'auth.session', 'role:admin']);

Route::view('/{path}', 'welcome')
    ->where('path', '^(?!api(?:/|$)|sanctum(?:/|$)|admin(?:/|$)).*')
    ->middleware(['auth', 'active', 'auth.session', 'role:user,moderator']);
