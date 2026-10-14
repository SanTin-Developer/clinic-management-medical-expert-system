<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the advisory risk score computed for a security event so it can be
     * surfaced to the account owner. Additive and nullable: existing rows are
     * unaffected and the score never drives authorization decisions.
     */
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropColumn('risk_score');
        });
    }
};
