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
        // The base appointments migration already creates `appointment_number`,
        // so this rename only applies to legacy databases that still have the
        // original `appointment_type` column. Guarding keeps fresh migrations
        // (and the SQLite test database) working on all environments.
        if (Schema::hasColumn('appointments', 'appointment_type')
            && ! Schema::hasColumn('appointments', 'appointment_number')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->renameColumn('appointment_type', 'appointment_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'appointment_number')
            && ! Schema::hasColumn('appointments', 'appointment_type')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->renameColumn('appointment_number', 'appointment_type');
            });
        }
    }
};
