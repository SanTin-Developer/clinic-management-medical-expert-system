<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The API treats every vital-sign measurement as optional, but `weight` and
     * `height` were created as NOT NULL, causing inserts without them to fail.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->change();
            $table->decimal('height', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable(false)->change();
            $table->decimal('height', 5, 2)->nullable(false)->change();
        });
    }
};
