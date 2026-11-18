<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'home'])->name('dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/rooms', [DashboardController::class, 'rooms'])->name('rooms');
    Route::get('/rooms/create', [DashboardController::class, 'createRoom'])->name('rooms.create');
    Route::post('/rooms', [DashboardController::class, 'storeRoom'])->name('rooms.store');
    Route::get('/billing', [FinanceController::class, 'billing'])->name('billing');
    Route::get('/billing/create', [FinanceController::class, 'createPayment'])->name('billing.create');
    Route::post('/billing', [FinanceController::class, 'storePayment'])->name('billing.store');
    Route::post('/billing/generate', [FinanceController::class, 'generateInvoices'])->name('billing.generate');
    Route::get('/billing/export', [FinanceController::class, 'exportBilling'])->name('billing.export');
    Route::patch('/billing/payments/{payment}/approve', [FinanceController::class, 'approvePayment'])->name('billing.payments.approve');
    Route::patch('/billing/payments/{payment}/reject', [FinanceController::class, 'rejectPayment'])->name('billing.payments.reject');
    Route::get('/expenses', [FinanceController::class, 'expenses'])->name('expenses');
    Route::get('/expenses/create', [FinanceController::class, 'createExpense'])->name('expenses.create');
    Route::post('/expenses', [FinanceController::class, 'storeExpense'])->name('expenses.store');
    Route::get('/expenses/report/export', [FinanceController::class, 'exportReport'])->name('expenses.report.export');
    Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('expenses.destroy');
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
    Route::get('/administrator', [DashboardController::class, 'administrator'])->name('administrator');
    Route::get('/administrator/users', [DashboardController::class, 'users'])->name('administrator.users');
    Route::get('/administrator/users/create', [DashboardController::class, 'createUser'])->name('administrator.users.create');
    Route::post('/administrator/users', [DashboardController::class, 'storeUser'])->name('administrator.users.store');
});

Route::get('/tenant/overview', [DashboardController::class, 'tenantOverview'])
    ->middleware(['auth', 'role:tenant'])
    ->name('tenant.overview');
