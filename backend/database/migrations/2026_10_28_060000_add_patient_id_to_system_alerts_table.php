<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_alerts', function (Blueprint $table) {
            $table->foreignId('patient_id')
                ->nullable()
                ->after('category')
                ->constrained('patients')
                ->nullOnDelete();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('system_alerts', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'status']);
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
