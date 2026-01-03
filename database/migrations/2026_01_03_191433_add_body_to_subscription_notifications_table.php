<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_notifications', function (Blueprint $table) {
            $table->text('body')->nullable()->after('recipient_name');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->integer('open_count')->default(0)->after('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_notifications', function (Blueprint $table) {
            $table->dropColumn(['body', 'opened_at', 'open_count']);
        });
    }
};
