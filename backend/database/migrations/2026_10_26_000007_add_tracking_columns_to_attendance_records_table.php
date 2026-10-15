<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend attendance_records with shift linkage, snapshotted schedule times,
 * lateness data, clock source, missing-clock-out flag and correction metadata.
 *
 * scheduled_start / scheduled_end are stored in UTC so lateness is computed
 * against an absolute instant (the night shift correctly spans midnight).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('user_id')->constrained('shifts')->nullOnDelete();
            $table->foreignId('shift_template_id')->nullable()->after('shift_id')->constrained('shift_templates')->nullOnDelete();
            $table->foreignId('clock_in_session_id')->nullable()->after('clock_out')->constrained('attendance_sessions')->nullOnDelete();
            $table->foreignId('clock_out_session_id')->nullable()->after('clock_in_session_id')->constrained('attendance_sessions')->nullOnDelete();

            $table->timestamp('scheduled_start')->nullable()->after('clock_out_session_id');
            $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');

            $table->unsignedSmallInteger('late_minutes')->nullable()->after('status');
            $table->string('late_band', 20)->nullable()->after('late_minutes');

            $table->string('clock_in_source', 20)->nullable()->after('late_band'); // qr, manual
            $table->string('clock_out_source', 20)->nullable()->after('clock_in_source');

            $table->boolean('missing_clock_out')->default(false)->after('clock_out_source');
            $table->timestamp('finalized_at')->nullable()->after('missing_clock_out');

            $table->foreignId('corrected_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable()->after('corrected_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('shift_template_id');
            $table->dropConstrainedForeignId('clock_in_session_id');
            $table->dropConstrainedForeignId('clock_out_session_id');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn([
                'scheduled_start',
                'scheduled_end',
                'late_minutes',
                'late_band',
                'clock_in_source',
                'clock_out_source',
                'missing_clock_out',
                'finalized_at',
                'corrected_at',
            ]);
        });
    }
};
