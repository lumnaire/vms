<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

// ── Public ──────────────────────────────────────────────────────
// Consumer/Guest: no login required
Route::get('/', fn() => redirect('/prices'));
Route::get('/prices', [\App\Http\Controllers\Public\PriceboardController::class, 'index'])
    ->name('prices.index');

// ── Auth ─────────────────────────────────────────────────────────
Route::get('/login',  [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
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
        Route::get('/fish-types',                 [\App\Http\Controllers\Supervisor\FishTypeController::class, 'index'])->name('fish-types.index');
        Route::post('/fish-types',                [\App\Http\Controllers\Supervisor\FishTypeController::class, 'store'])->name('fish-types.store');
        Route::put('/fish-types/{fishType}',      [\App\Http\Controllers\Supervisor\FishTypeController::class, 'update'])->name('fish-types.update');
        Route::patch('/fish-types/{fishType}/toggle', [\App\Http\Controllers\Supervisor\FishTypeController::class, 'toggleStatus'])->name('fish-types.toggle');
        Route::delete('/fish-types/{fishType}',   [\App\Http\Controllers\Supervisor\FishTypeController::class, 'destroy'])->name('fish-types.destroy');

        Route::get('/price-guides',                [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'index'])->name('price-guides.index');
        Route::post('/price-guides',               [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'store'])->name('price-guides.store');
        Route::put('/price-guides/{priceGuide}',   [\App\Http\Controllers\Supervisor\PriceGuideController::class, 'update'])->name('price-guides.update');
        Route::delete('/price-guides/{priceGuide}',[\App\Http\Controllers\Supervisor\PriceGuideController::class, 'destroy'])->name('price-guides.destroy');

        Route::get('/forecasts', [\App\Http\Controllers\Supervisor\ForecastController::class, 'index'])->name('forecasts.index');
        Route::get('/reports',   [\App\Http\Controllers\Supervisor\ReportController::class,   'index'])->name('reports.index');
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

        // ── Vendor Account Management ──────────────────────────────
        Route::get('/vendors',                 [\App\Http\Controllers\Staff\VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors',                [\App\Http\Controllers\Staff\VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{user}',          [\App\Http\Controllers\Staff\VendorController::class, 'update'])->name('vendors.update');
        Route::patch('/vendors/{user}/toggle', [\App\Http\Controllers\Staff\VendorController::class, 'toggleStatus'])->name('vendors.toggle');
        Route::delete('/vendors/{user}',       [\App\Http\Controllers\Staff\VendorController::class, 'destroy'])->name('vendors.destroy');

        // ── Records ────────────────────────────────────────────────
        Route::get('/price-guides', [\App\Http\Controllers\Staff\PriceGuideController::class, 'index'])->name('price-guides.index');
        Route::get('/reports',      [\App\Http\Controllers\Staff\ReportController::class,     'index'])->name('reports.index');
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
        Route::patch('/inventory/{inventory}/sold',  [\App\Http\Controllers\Vendor\InventoryController::class, 'updateSold'])->name('inventory.updateSold');
    });