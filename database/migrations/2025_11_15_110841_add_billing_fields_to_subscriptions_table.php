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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('customer_country', 2)->nullable()->after('customer_name');
            $table->string('customer_tax_id_type', 50)->nullable()->after('customer_country');
            $table->string('customer_tax_id', 255)->nullable()->after('customer_tax_id_type');
            $table->decimal('amount_usd', 12, 2)->nullable()->after('amount_total');
            $table->decimal('amount_ars', 12, 2)->nullable()->after('amount_usd');
            $table->decimal('amount_eur', 12, 2)->nullable()->after('amount_ars');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'customer_country',
                'customer_tax_id_type',
                'customer_tax_id',
                'amount_usd',
                'amount_ars',
                'amount_eur',
            ]);
        });
    }
};
