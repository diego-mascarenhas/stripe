<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Help page
Route::get('/help/subscription-system', function () {
    return view('help.subscription-system');
})->name('help.subscription-system');

// Stripe Webhook (excluded from CSRF in bootstrap/app.php)
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');

// Tracking pixel for email notifications
Route::get('/track/{token}', function ($token) {
    \Log::info('Tracking: token recibido', ['token' => $token]);
    
    try {
        // Buscar notificación por token
        $notification = \App\Models\SubscriptionNotification::all()->first(function ($n) use ($token) {
            return hash_equals($n->getTrackingToken(), $token);
        });

        if ($notification) {
            \Log::info('Tracking: notificación encontrada', ['id' => $notification->id]);

            // Registrar apertura usando inserción directa en BD
            \DB::table('subscription_notifications')
                ->where('id', $notification->id)
                ->whereNull('opened_at')
                ->update([
                    'opened_at' => now(),
                    'updated_at' => now(),
                ]);

            // Incrementar contador de aperturas
            \DB::table('subscription_notifications')
                ->where('id', $notification->id)
                ->increment('open_count');

            \Log::info('Tracking: apertura registrada', [
                'notification_id' => $notification->id,
                'recipient' => $notification->recipient_email,
            ]);
        } else {
            \Log::warning('Tracking: notificación NO encontrada para token', ['token' => substr($token, 0, 10) . '...']);
        }
    } catch (\Throwable $e) {
        \Log::error('Tracking: error al procesar', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    // Siempre retornar pixel transparente 1x1 con headers anti-cache
    return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
        ->header('Content-Type', 'image/gif')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->name('notification.track.pixel');

