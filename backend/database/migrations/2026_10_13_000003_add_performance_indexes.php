<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes that back the hot lookups and report groupings used by the
     * application. Strictly additive; no schema changes.
     */
    public function up(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->index(['user_id', 'token_hash']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paid_at', 'method']);
        });
    }

    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'token_hash']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at', 'method']);
        });
    }
};
