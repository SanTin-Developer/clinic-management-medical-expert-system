<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields required to complete nursing tasks with their clinical record.
 *
 * - vital_signs: inpatient vitals are not tied to an appointment, so the
 *   appointment becomes optional and the record can link to the admission and
 *   the nursing task that produced it.
 * - nursing_tasks: records the completion outcome (and reason for exceptions).
 * - medication_administrations: links a scheduled dose to the nursing task that
 *   requested it, and keeps a free-text route when "other" is selected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->change();
            $table->foreignId('admission_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('admissions')
                ->nullOnDelete();
            $table->foreignId('nursing_task_id')
                ->nullable()
                ->after('admission_id')
                ->constrained('nursing_tasks')
                ->nullOnDelete();

            $table->index('admission_id');
            $table->index('nursing_task_id');
        });

        Schema::table('nursing_tasks', function (Blueprint $table) {
            $table->string('outcome', 30)->nullable()->after('status');
            $table->text('outcome_reason')->nullable()->after('outcome');
        });

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->foreignId('nursing_task_id')
                ->nullable()
                ->after('admission_id')
                ->constrained('nursing_tasks')
                ->nullOnDelete();
            $table->string('route_detail', 100)->nullable()->after('route');

            $table->index('nursing_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropIndex(['nursing_task_id']);
            $table->dropConstrainedForeignId('nursing_task_id');
            $table->dropColumn('route_detail');
        });

        Schema::table('nursing_tasks', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'outcome_reason']);
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropIndex(['admission_id']);
            $table->dropIndex(['nursing_task_id']);
            $table->dropConstrainedForeignId('admission_id');
            $table->dropConstrainedForeignId('nursing_task_id');
            $table->foreignId('appointment_id')->nullable(false)->change();
        });
    }
};
