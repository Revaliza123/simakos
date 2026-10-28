<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'overview'])->name('dashboard');
Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
Route::get('/rooms', [DashboardController::class, 'rooms'])->name('rooms');
Route::get('/rooms/create', [DashboardController::class, 'createRoom'])->name('rooms.create');
Route::post('/rooms', [DashboardController::class, 'storeRoom'])->name('rooms.store');
Route::get('/billing', [DashboardController::class, 'billing'])->name('billing');
Route::get('/billing/create', [DashboardController::class, 'createPayment'])->name('billing.create');
Route::post('/billing', [DashboardController::class, 'storePayment'])->name('billing.store');
Route::get('/billing/export', [DashboardController::class, 'exportBilling'])->name('billing.export');
Route::get('/complaints', [DashboardController::class, 'complaints'])->name('complaints');
Route::get('/complaints/create', [DashboardController::class, 'createComplaint'])->name('complaints.create');
Route::post('/complaints', [DashboardController::class, 'storeComplaint'])->name('complaints.store');
Route::get('/announcements', [DashboardController::class, 'announcements'])->name('announcements');
Route::get('/announcements/create', [DashboardController::class, 'createAnnouncement'])->name('announcements.create');
Route::post('/announcements', [DashboardController::class, 'storeAnnouncement'])->name('announcements.store');
Route::get('/tenants/create', [DashboardController::class, 'createTenant'])->name('tenants.create');
Route::post('/tenants', [DashboardController::class, 'storeTenant'])->name('tenants.store');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
