<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\PriceboardController;
use App\Http\Controllers\Staff\ConfirmationController;
use App\Http\Controllers\Supervisor\AccountController;
use App\Http\Controllers\Supervisor\DashboardController;
use App\Http\Controllers\Supervisor\FishTypeController;
use App\Http\Controllers\Supervisor\ForecastController;
use App\Http\Controllers\Supervisor\PriceGuideController;
use App\Http\Controllers\Supervisor\ReportController;
use App\Http\Controllers\Supervisor\StaffController;
use App\Http\Controllers\Supervisor\VendorController;
use App\Http\Controllers\Vendor\InventoryController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────────
// Consumer/Guest: no login required. The board IS the home page — there is
// no separate /prices route; signing in happens from the navbar on "/".
Route::get('/', [PriceboardController::class, 'index'])
    ->name('home');

// ── Auth ─────────────────────────────────────────────────────────
// Signing in happens from the navbar form on "/", which POSTs here. There is no
// standalone sign-in page, so an old link or a remembered bookmark that still
// says /login is sent back to the board rather than shown an error.
Route::get('/login', fn () => redirect()->route('home'));
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── Supervisor ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Vendor Management ──────────────────────────────────────
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{user}', [VendorController::class, 'update'])->name('vendors.update');
        Route::patch('/vendors/{user}/toggle', [VendorController::class, 'toggleStatus'])->name('vendors.toggle');
        Route::delete('/vendors/{user}', [VendorController::class, 'destroy'])->name('vendors.destroy');

        // ── Staff Management ───────────────────────────────────────
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{user}/toggle', [StaffController::class, 'toggleStatus'])->name('staff.toggle');
        Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');

        // ── Analytics ──────────────────────────────────────────────
        // ── Fish Type Management ───────────────────────────────────
        // Edit only: there is no activate/deactivate or delete, so a fish type
        // that is listed is in use until the supervisor corrects it.
        Route::get('/fish-types', [FishTypeController::class, 'index'])->name('fish-types.index');
        Route::post('/fish-types', [FishTypeController::class, 'store'])->name('fish-types.store');
        Route::put('/fish-types/{fishType}', [FishTypeController::class, 'update'])->name('fish-types.update');

        Route::get('/price-guides', [PriceGuideController::class, 'index'])->name('price-guides.index');
        Route::post('/price-guides', [PriceGuideController::class, 'store'])->name('price-guides.store');
        Route::put('/price-guides/{priceGuide}', [PriceGuideController::class, 'update'])->name('price-guides.update');
        Route::delete('/price-guides/{priceGuide}', [PriceGuideController::class, 'destroy'])->name('price-guides.destroy');

        Route::get('/forecasts', [ForecastController::class, 'index'])->name('forecasts.index');
        Route::get('/reports', [ReportController::class,   'index'])->name('reports.index');

        // ── My Account ─────────────────────────────────────────────
        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    });

// ── Staff ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {

        Route::get('/dashboard', [App\Http\Controllers\Staff\DashboardController::class, 'index'])->name('dashboard');

        // ── Price Confirmation Workflow ────────────────────────────
        Route::get('/confirmations', [ConfirmationController::class, 'index'])->name('confirmations.index');
        Route::patch('/confirmations/{inventory}/approve', [ConfirmationController::class, 'approve'])->name('confirmations.approve');
        Route::patch('/confirmations/{inventory}/reject', [ConfirmationController::class, 'reject'])->name('confirmations.reject');

        // ── Vendor Account Management (creation is supervisor-only) ──
        Route::get('/vendors', [App\Http\Controllers\Staff\VendorController::class, 'index'])->name('vendors.index');
        Route::put('/vendors/{user}', [App\Http\Controllers\Staff\VendorController::class, 'update'])->name('vendors.update');
        Route::patch('/vendors/{user}/toggle', [App\Http\Controllers\Staff\VendorController::class, 'toggleStatus'])->name('vendors.toggle');
        Route::delete('/vendors/{user}', [App\Http\Controllers\Staff\VendorController::class, 'destroy'])->name('vendors.destroy');

        // ── Records ────────────────────────────────────────────────
        Route::get('/price-guides', [App\Http\Controllers\Staff\PriceGuideController::class, 'index'])->name('price-guides.index');
        // Supply report: daily / monthly / yearly, previewed here and downloaded as PDF.
        Route::get('/reports', [App\Http\Controllers\Staff\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/pdf', [App\Http\Controllers\Staff\ReportController::class, 'pdf'])->name('reports.pdf');

    });

// ── Vendor ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:vendor'])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function () {

        Route::get('/dashboard', [App\Http\Controllers\Vendor\DashboardController::class,  'index'])->name('dashboard');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

        // ── Batch actions ─────────────────────────────────────────────
        // Release records kilograms sold from a confirmed batch, which comes off
        // the batch's remaining stock and the public board. Write-off clears a
        // batch that has passed its freshness window.
        Route::post('/inventory/{inventory}/release', [InventoryController::class, 'release'])->name('inventory.release');
        Route::post('/inventory/{inventory}/write-off', [InventoryController::class, 'writeOff'])->name('inventory.write-off');
    });
