<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for nurse assignment lifecycle events (created,
 * modified, reassigned, ended). Kept separate from attendance_audit_logs and
 * security_events, which have different responsibilities.
 *
 * Rows are only ever inserted by the AssignmentAudit service and are never
 * deleted or updated by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('patient_assignments')
                ->nullOnDelete();

            $table->string('action', 40);
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('assignment_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_audit_logs');
    }
};
