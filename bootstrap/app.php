<?php

// ─────────────────────────────────────────────────────────────────
//  FILE: bootstrap/app.php
//  Add the 'role' middleware alias inside ->withMiddleware()
// ─────────────────────────────────────────────────────────────────

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

   
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
       

    })
    ->withSchedule(function (Schedule $schedule) {
        // Generate ARIMA forecasts every day at midnight
        $schedule->command('forecast:generate')->dailyAt('00:01');

        // Lock previous-day inventory entries every day at 00:05
        $schedule->command('inventory:lock')->dailyAt('00:05');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();