<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\TenantPortalController;
use Illuminate\Support\Facades\Route;

/*
| Halaman publik (Guest / Calon Penghuni): katalog kamar kosong.
*/
Route::get('/', LandingController::class)->name('home');
Route::get('/dashboard', [AuthController::class, 'home'])->name('dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
| Dashboard Admin (pemilik kos) — semua rute di bawah /admin/*.
*/
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
    Route::redirect('/dashboard', '/admin/overview')->name('admin.dashboard');

    // UC-02 Kelola Master Kamar (FR-02)
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

    Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
    Route::get('/room-types/create', [RoomTypeController::class, 'create'])->name('room-types.create');
    Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
    Route::get('/room-types/{roomType}/edit', [RoomTypeController::class, 'edit'])->name('room-types.edit');
    Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
    Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

    // UC-03 Kelola Penyewaan Kamar: check-in / check-out (FR-03)
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
    Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    Route::patch('/rentals/{rental}/billing-cycle', [RentalController::class, 'updateBillingCycle'])->name('rentals.billing-cycle');
    Route::patch('/rentals/{rental}/checkout', [RentalController::class, 'checkout'])->name('rentals.checkout');
    Route::get('/tenants/create', [RentalController::class, 'createTenant'])->name('tenants.create');
    Route::post('/tenants', [RentalController::class, 'storeTenant'])->name('tenants.store');

    // UC-05 Verifikasi Pembayaran (FR-06) & rekap keuangan
    Route::get('/billing', [FinanceController::class, 'billing'])->name('billing');
    Route::get('/billing/create', [FinanceController::class, 'createPayment'])->name('billing.create');
    Route::post('/billing', [FinanceController::class, 'storePayment'])->name('billing.store');
    Route::get('/billing/charges', [FinanceController::class, 'charges'])->name('billing.charges');
    Route::get('/billing/charges/create', [FinanceController::class, 'createCharge'])->name('billing.charges.create');
    Route::post('/billing/charges', [FinanceController::class, 'storeCharge'])->name('billing.charges.store');
    Route::patch('/billing/charges/{charge}/stop', [FinanceController::class, 'stopCharge'])->name('billing.charges.stop');
    Route::delete('/billing/charges/{charge}', [FinanceController::class, 'destroyCharge'])->name('billing.charges.destroy');
    Route::post('/billing/generate', [FinanceController::class, 'generateInvoices'])->name('billing.generate');
    Route::get('/billing/export', [FinanceController::class, 'exportBilling'])->name('billing.export');
    Route::patch('/billing/payments/{payment}/approve', [FinanceController::class, 'approvePayment'])->name('billing.payments.approve');
    Route::patch('/billing/payments/{payment}/reject', [FinanceController::class, 'rejectPayment'])->name('billing.payments.reject');

    // UC-08 Pengeluaran (FR-10)
    Route::get('/expenses', [FinanceController::class, 'expenses'])->name('expenses');
    Route::get('/expenses/create', [FinanceController::class, 'createExpense'])->name('expenses.create');
    Route::post('/expenses', [FinanceController::class, 'storeExpense'])->name('expenses.store');
    Route::get('/expenses/report/export', [FinanceController::class, 'exportReport'])->name('expenses.report.export');
    Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('expenses.destroy');

    // UC-07 Tindak Lanjuti Keluhan (FR-08)
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints');
    Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::patch('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');

    // UC-08 Pengumuman (FR-09)
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
    Route::get('/administrator', [DashboardController::class, 'administrator'])->name('administrator');
    Route::get('/administrator/users', [DashboardController::class, 'users'])->name('administrator.users');
    Route::get('/administrator/users/create', [DashboardController::class, 'createUser'])->name('administrator.users.create');
    Route::post('/administrator/users', [DashboardController::class, 'storeUser'])->name('administrator.users.store');
});

/*
| Portal Tenant (penghuni) — semua rute di bawah /tenant/*.
| Setiap query dibatasi pada data milik penghuni yang login (NFR-03).
*/
Route::prefix('tenant')->name('tenant.')->middleware(['auth', 'role:tenant'])->group(function (): void {
    Route::get('/overview', [TenantPortalController::class, 'overview'])->name('overview');

    // UC-04 Kirim Bukti Pembayaran (FR-04, FR-05)
    Route::get('/bills', [TenantPortalController::class, 'bills'])->name('bills');
    Route::post('/bills/payments', [TenantPortalController::class, 'storePayment'])->name('payments.store');

    // UC-06 Ajukan Keluhan Fasilitas (FR-07)
    Route::get('/complaints', [TenantPortalController::class, 'complaints'])->name('complaints');
    Route::get('/complaints/create', [TenantPortalController::class, 'createComplaint'])->name('complaints.create');
    Route::post('/complaints', [TenantPortalController::class, 'storeComplaint'])->name('complaints.store');

    Route::get('/announcements', [TenantPortalController::class, 'announcements'])->name('announcements');
});
