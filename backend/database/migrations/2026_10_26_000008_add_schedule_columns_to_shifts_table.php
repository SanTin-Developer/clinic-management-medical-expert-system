<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link shifts to a template and mark overnight/published state. Together with
 * the (template, date) index this lets the scheduler detect overlaps and
 * duplicate assignments and identify uncovered shifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('shift_template_id')->nullable()->after('user_id')->constrained('shift_templates')->nullOnDelete();
            $table->boolean('crosses_midnight')->default(false)->after('end_time');
            $table->timestamp('published_at')->nullable()->after('status');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->index(['shift_template_id', 'shift_date']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['shift_template_id', 'shift_date']);
            $table->dropIndex(['published_at']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_template_id');
            $table->dropColumn(['crosses_midnight', 'published_at']);
        });
    }
};
