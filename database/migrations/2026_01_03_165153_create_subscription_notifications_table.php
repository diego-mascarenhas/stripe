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
        Schema::create('subscription_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->enum('notification_type', [
                'warning_5_days',    // 5 días antes del vencimiento
                'warning_2_days',    // 2 días antes del vencimiento
                'suspended',         // Servicio suspendido
                'reactivated',       // Servicio reactivado
            ]);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable(); // Cuándo se debe enviar
            $table->timestamp('sent_at')->nullable();      // Cuándo se envió
            $table->string('recipient_email');
            $table->string('recipient_name');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Datos adicionales del email
            $table->timestamps();

            // Índices con nombres personalizados más cortos
            $table->index(['subscription_id', 'notification_type'], 'sub_notif_sub_type_idx');
            $table->index(['status', 'scheduled_at'], 'sub_notif_status_sched_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_notifications');
    }
};
