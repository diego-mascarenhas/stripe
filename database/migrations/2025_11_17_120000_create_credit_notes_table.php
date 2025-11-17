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
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique();
            $table->string('stripe_invoice_id')->nullable()->index();
            $table->string('stripe_refund_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_description')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->string('customer_address_country', 3)->nullable();
            $table->string('number')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('type')->nullable();
            $table->string('reason')->nullable();
            $table->string('currency', 3)->default('usd');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('credit_note_created_at')->nullable()->index();
            $table->boolean('voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->string('pdf')->nullable();
            $table->string('hosted_credit_note_url')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'credit_note_created_at']);
            $table->index(['customer_id', 'credit_note_created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};

