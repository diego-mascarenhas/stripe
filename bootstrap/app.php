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
        // Currency rates: Ejecutar cada hora, sincronizar si han pasado 1 hora desde la última actualización
        $schedule->command('currency:sync')
            ->hourly()
            ->withoutOverlapping(10)
            ->when(function () {
                $lastRate = \App\Models\ExchangeRate::latest('created_at')->first();
                // Si no hay registros, ejecutar inmediatamente
                if (!$lastRate) {
                    return true;
                }
                // Ejecutar si ha pasado 1 hora o más
                return $lastRate->created_at->diffInHours(now()) >= 1;
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Currency rates synchronized successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Currency rates sync failed');
            });

        // Subscriptions: Ejecutar cada 4 horas
        $schedule->command('subscriptions:sync')
            ->cron('0 */4 * * *')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Subscriptions synchronized successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Subscriptions sync failed');
            });

        // Invoices: Ejecutar cada 4 horas (desplazado 15 minutos)
        $schedule->command('invoices:sync')
            ->cron('15 */4 * * *')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Invoices synchronized successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Invoices sync failed');
            });

        // Credit Notes: Ejecutar cada 4 horas (desplazado 30 minutos)
        $schedule->command('creditnotes:sync')
            ->cron('30 */4 * * *')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Credit notes synchronized successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Credit notes sync failed');
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
