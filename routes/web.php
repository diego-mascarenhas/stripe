<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stripe Webhook (excluded from CSRF in bootstrap/app.php)
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');

// Tracking pixel for email notifications
Route::get('/track/notification/{notification}', function ($notificationId) {
    try {
        $notification = \App\Models\SubscriptionNotification::findOrFail($notificationId);
        
        // Registrar apertura
        if (!$notification->opened_at) {
            $notification->opened_at = now();
        }
        $notification->increment('open_count');
        
        // Retornar pixel transparente 1x1
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    } catch (\Throwable $e) {
        // Retornar pixel vacío incluso si hay error
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
            ->header('Content-Type', 'image/gif');
    }
})->name('notification.track.pixel');

