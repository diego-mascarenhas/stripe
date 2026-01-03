<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar envío de notificaciones de suscripciones
Schedule::command('subscriptions:send-notifications')
    ->daily()
    ->at('09:00')
    ->emailOutputOnFailure(config('mail.from.address'));
