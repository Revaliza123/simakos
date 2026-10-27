<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'overview'])->name('dashboard');
Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
Route::get('/rooms', [DashboardController::class, 'rooms'])->name('rooms');
Route::get('/billing', [DashboardController::class, 'billing'])->name('billing');
Route::get('/complaints', [DashboardController::class, 'complaints'])->name('complaints');
Route::get('/announcements', [DashboardController::class, 'announcements'])->name('announcements');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
