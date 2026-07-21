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
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('fiscal_number')->nullable()->unique()->after('number');
            $table->string('fiscal_series', 32)->nullable()->index()->after('fiscal_number');
            $table->unsignedInteger('fiscal_sequence')->nullable()->after('fiscal_series');

            $table->index(['fiscal_series', 'fiscal_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropIndex(['fiscal_series', 'fiscal_sequence']);
            $table->dropColumn(['fiscal_number', 'fiscal_series', 'fiscal_sequence']);
        });
    }
};
