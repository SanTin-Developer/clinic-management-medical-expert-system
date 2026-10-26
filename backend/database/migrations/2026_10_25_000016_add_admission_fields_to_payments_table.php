<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->after('invoice_id')->constrained('admissions')->nullOnDelete();
            $table->foreignId('admission_deposit_id')->nullable()->after('admission_id')->constrained('admission_deposits')->nullOnDelete();
            $table->index(['admission_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['admission_id']);
            $table->dropForeign(['admission_id']);
            $table->dropForeign(['admission_deposit_id']);
            $table->dropColumn(['admission_id', 'admission_deposit_id']);
        });
    }
};
