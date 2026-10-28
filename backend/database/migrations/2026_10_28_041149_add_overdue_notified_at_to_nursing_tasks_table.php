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
        Schema::table('nursing_tasks', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nursing_tasks', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_at');
        });
    }
};
