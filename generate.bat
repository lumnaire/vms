@echo off
echo ============================================
echo  VPM System - Artisan File Generator
echo ============================================

:: ── Models ───────────────────────────────────
echo.
echo [1/5] Creating Models...
php artisan make:model ActivityLog
php artisan make:model FishType
php artisan make:model Forecast
php artisan make:model PriceGuide
php artisan make:model Report
php artisan make:model VendorInventory
php artisan make:model VendorProfile

:: ── Seeders ──────────────────────────────────
echo.
echo [2/5] Creating Seeders...
php artisan make:seeder UserSeeder
php artisan make:seeder FishTypeSeeder
php artisan make:seeder PriceGuideSeeder

:: ── Middleware ───────────────────────────────
echo.
echo [3/5] Creating Middleware...
php artisan make:middleware RoleMiddleware

:: ── Auth Controller ──────────────────────────
echo.
echo [4/5] Creating Auth Controller...
php artisan make:controller Auth/LoginController

:: ── Role Controllers ─────────────────────────
echo.
echo [5/5] Creating Role Controllers...

:: Supervisor
php artisan make:controller Supervisor/DashboardController
php artisan make:controller Supervisor/StaffController
php artisan make:controller Supervisor/VendorController
php artisan make:controller Supervisor/ForecastController
php artisan make:controller Supervisor/ReportController

:: Staff
php artisan make:controller Staff/DashboardController
php artisan make:controller Staff/ConfirmationController
php artisan make:controller Staff/VendorController
php artisan make:controller Staff/PriceGuideController
php artisan make:controller Staff/ReportController

:: Vendor
php artisan make:controller Vendor/DashboardController
php artisan make:controller Vendor/InventoryController

:: Public (no auth)
php artisan make:controller Public/PriceboardController

:: ─────────────────────────────────────────────
echo.
echo ============================================
echo  Done! All files generated.
echo  Now replace contents with provided code.
echo ============================================
pause