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
        // Currency rates: Ejecutar cada hora, pero solo sincronizar si han pasado más de 23 horas desde la última actualización
        $schedule->command('currency:sync')
            ->hourly()
            ->withoutOverlapping(10)
            ->when(function () {
                $lastRate = \App\Models\ExchangeRate::latest('created_at')->first();
                // Si no hay registros, ejecutar inmediatamente
                if (!$lastRate) {
                    return true;
                }
                // Solo ejecutar si han pasado más de 23 horas
                return $lastRate->created_at->diffInHours(now()) >= 23;
            })
            ->onSuccess(function () {
                \Log::info('Currency rates synchronized successfully');
            })
            ->onFailure(function () {
                \Log::error('Currency rates sync failed');
            });

        $schedule->command('subscriptions:sync')->dailyAt('06:15');
        $schedule->command('invoices:sync')->dailyAt('06:30');
        $schedule->command('creditnotes:sync')->dailyAt('06:45');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
