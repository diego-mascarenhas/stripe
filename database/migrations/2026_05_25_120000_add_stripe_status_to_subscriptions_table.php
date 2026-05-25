<?php

use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STRIPE_ONLY_STATUSES = [
        'past_due',
        'unpaid',
        'canceled',
        'trialing',
        'incomplete',
        'incomplete_expired',
    ];

    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_status')->nullable()->after('status');
        });

        Subscription::query()->each(function (Subscription $subscription): void {
            $legacyStatus = $subscription->status;
            $stripeStatus = data_get($subscription->raw_payload, 'status', $legacyStatus);

            if ($subscription->type === 'buy') {
                $subscription->forceFill([
                    'stripe_status' => null,
                ])->saveQuietly();

                return;
            }

            $operationalStatus = $legacyStatus === 'paused'
                ? 'paused'
                : (in_array($legacyStatus, self::STRIPE_ONLY_STATUSES, true) ? 'active' : $legacyStatus);

            $subscription->forceFill([
                'stripe_status' => $stripeStatus,
                'status' => $operationalStatus,
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_status');
        });
    }
};
