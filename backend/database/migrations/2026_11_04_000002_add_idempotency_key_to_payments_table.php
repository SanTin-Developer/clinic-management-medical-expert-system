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
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'idempotency_key')) {
                $table->uuid('idempotency_key')
                    ->nullable()
                    ->after('reference');
            }
        });

        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['invoice_id', 'idempotency_key']);
            });
        } catch (Throwable $e) {
            // Unique constraint may already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_id', 'idempotency_key']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
