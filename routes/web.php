<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

// ── Public ──────────────────────────────────────────────────────
// Consumer/Guest: no login required. The board IS the home page — there is
// no separate /prices route; signing in happens from the navbar on "/".
Route::get('/', [\App\Http\Controllers\Public\PriceboardController::class, 'index'])
    ->name('home');

// ── Auth ─────────────────────────────────────────────────────────
// Signing in happens from the navbar form on "/", which POSTs here. There is no
// standalone sign-in page, so an old link or a remembered bookmark that still
// says /login is sent back to the board rather than shown an error.
Route::get('/login', fn () => redirect()->route('home'));
Route::post('/login',  [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── Supervisor ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {

        Route::get('/dashboard', [\App\Http\Controllers\Supervisor\DashboardController::class, 'index'])->name('dashboard');

        // ── Vendor Management ──────────────────────────────────────
        Route::get('/vendors',                 [\App\Http\Controllers\Supervisor\VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors',                [\App\Http\Controllers\Supervisor\VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{user}',          [\App\Http\Controllers\Supervisor\VendorController::class, 'update'])->name('vendors.update');
        Route::patch('/vendors/{user}/toggle', [\App\Http\Controllers\Supervisor\VendorController::class, 'toggleStatus'])->name('vendors.toggle');
        Route::delete('/vendors/{user}',       [\App\Http\Controllers\Supervisor\VendorController::class, 'destroy'])->name('vendors.destroy');

        // ── Staff Management ───────────────────────────────────────
        Route::get('/staff',                 [\App\Http\Controllers\Supervisor\StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff',                [\App\Http\Controllers\Supervisor\StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{user}',          [\App\Http\Controllers\Supervisor\StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{user}/toggle', [\App\Http\Controllers\Supervisor\StaffController::class, 'toggleStatus'])->name('staff.toggle');
        Route::delete('/staff/{user}',       [\App\Http\Controllers\Supervisor\StaffController::class, 'destroy'])->name('staff.destroy');

        // ── Analytics ──────────────────────────────────────────────
        // ── Fish Type Management ───────────────────────────────────
        // Edit only: there is no activate/deactivate or delete, so a fish type
        // that is listed is in use until the supervisor corrects it.
        Route::get('/fish-types',                 [\App\Http\Controllers\Supervisor\FishTypeController::class, 'index'])->name('fish-types.index');
        Route::post('/fish-types',                [\App\Http\Controllers\Supervisor\FishTypeController::class, 'store'])->name('fish-types.store');
        Route::put('/fish-types/{fishType}',      [\App\Http\Controllers\Supervisor\FishTypeController::class, 'update'])->name('fish-types.update');

        Route::get('/price-guides',                [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'index'])->name('price-guides.index');
        Route::post('/price-guides',               [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'store'])->name('price-guides.store');
        Route::put('/price-guides/{priceGuide}',   [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'update'])->name('price-guides.update');
        Route::delete('/price-guides/{priceGuide}',[\App\Http\Controllers\Supervisor\PriceGuideController::class, 'destroy'])->name('price-guides.destroy');

        Route::get('/forecasts', [\App\Http\Controllers\Supervisor\ForecastController::class, 'index'])->name('forecasts.index');
        Route::get('/reports',   [\App\Http\Controllers\Supervisor\ReportController::class,   'index'])->name('reports.index');

        // ── Vendor Sale Reports ─────────────────────────────────────
        Route::get('/sale-reports', [\App\Http\Controllers\SaleReportController::class, 'index'])->name('sale-reports.index');

        // ── My Account ─────────────────────────────────────────────
        Route::get('/account',           [\App\Http\Controllers\Supervisor\AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account/profile',   [\App\Http\Controllers\Supervisor\AccountController::class, 'updateProfile'])->name('account.profile');
        Route::put('/account/password',  [\App\Http\Controllers\Supervisor\AccountController::class, 'updatePassword'])->name('account.password');
    });

// ── Staff ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {

        Route::get('/dashboard', [\App\Http\Controllers\Staff\DashboardController::class, 'index'])->name('dashboard');

        // ── Price Confirmation Workflow ────────────────────────────
        Route::get('/confirmations',                        [\App\Http\Controllers\Staff\ConfirmationController::class, 'index'])->name('confirmations.index');
        Route::patch('/confirmations/{inventory}/approve',  [\App\Http\Controllers\Staff\ConfirmationController::class, 'approve'])->name('confirmations.approve');
        Route::patch('/confirmations/{inventory}/reject',   [\App\Http\Controllers\Staff\ConfirmationController::class, 'reject'])->name('confirmations.reject');

        // ── Vendor Account Management (creation is supervisor-only) ──
        Route::get('/vendors',                 [\App\Http\Controllers\Staff\VendorController::class, 'index'])->name('vendors.index');
        Route::put('/vendors/{user}',          [\App\Http\Controllers\Staff\VendorController::class, 'update'])->name('vendors.update');
        Route::patch('/vendors/{user}/toggle', [\App\Http\Controllers\Staff\VendorController::class, 'toggleStatus'])->name('vendors.toggle');
        Route::delete('/vendors/{user}',       [\App\Http\Controllers\Staff\VendorController::class, 'destroy'])->name('vendors.destroy');

        // ── Records ────────────────────────────────────────────────
        Route::get('/price-guides', [\App\Http\Controllers\Staff\PriceGuideController::class, 'index'])->name('price-guides.index');
        Route::get('/reports',      [\App\Http\Controllers\Staff\ReportController::class,     'index'])->name('reports.index');

        // ── Vendor Sale Reports ─────────────────────────────────────
        Route::get('/sale-reports', [\App\Http\Controllers\SaleReportController::class, 'index'])->name('sale-reports.index');
    });

// ── Vendor ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:vendor'])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function () {

        Route::get('/dashboard',  [\App\Http\Controllers\Vendor\DashboardController::class,  'index'])->name('dashboard');
        Route::get('/inventory',     [\App\Http\Controllers\Vendor\InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory',    [\App\Http\Controllers\Vendor\InventoryController::class, 'store'])->name('inventory.store');
        Route::delete('/inventory/{inventory}', [\App\Http\Controllers\Vendor\InventoryController::class, 'destroy'])->name('inventory.destroy');

        // ── Sale Report ───────────────────────────────────────────────
        // The vendor declares the day's sales against the entries staff
        // confirmed. Closes at 11:59 PM on the report date; `sold_kg` is no
        // longer edited entry-by-entry.
        Route::get('/sale-report',  [\App\Http\Controllers\Vendor\SaleReportController::class, 'index'])->name('sale-report.index');
        Route::post('/sale-report', [\App\Http\Controllers\Vendor\SaleReportController::class, 'store'])->name('sale-report.store');
    });
