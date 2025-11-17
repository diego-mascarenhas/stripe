<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('currency:sync')->dailyAt('06:00');
        $schedule->command('subscriptions:sync')->dailyAt('06:15');
        $schedule->command('invoices:sync')->dailyAt('06:30');
        $schedule->command('creditnotes:sync')->dailyAt('06:45');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
