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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('mercadopago_id')->unique();
            $table->string('external_reference')->nullable()->index();
            $table->string('payment_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('status_detail')->nullable();
            
            // Payer information
            $table->string('payer_id')->nullable()->index();
            $table->string('payer_email')->nullable()->index();
            $table->string('payer_first_name')->nullable();
            $table->string('payer_last_name')->nullable();
            $table->string('payer_identification_type')->nullable();
            $table->string('payer_identification_number')->nullable();
            
            // Amount details
            $table->string('currency', 3)->default('ars');
            $table->decimal('transaction_amount', 12, 2)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('total_paid_amount', 12, 2)->nullable();
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->decimal('mercadopago_fee', 12, 2)->nullable();
            
            // Dates
            $table->timestamp('payment_created_at')->nullable()->index();
            $table->timestamp('payment_approved_at')->nullable()->index();
            $table->timestamp('money_release_date')->nullable();
            
            // Additional information
            $table->text('description')->nullable();
            $table->integer('installments')->default(1);
            $table->string('issuer_id')->nullable();
            $table->string('operation_type')->nullable();
            $table->boolean('live_mode')->default(true);
            $table->boolean('captured')->default(true);
            
            // Metadata and sync
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            
            // Additional indexes
            $table->index(['status', 'payment_created_at']);
            $table->index(['payer_email', 'payment_created_at']);
            $table->index(['external_reference', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

