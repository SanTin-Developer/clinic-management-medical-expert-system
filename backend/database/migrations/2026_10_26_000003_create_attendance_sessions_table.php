<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rotating QR attendance session opened by an administrator for a shift
 * template on a given date and action (clock_in or clock_out).
 *
 * A single row exists per (template, date, action): reopening a closed session
 * updates the same row so no duplicate open sessions can coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shift_template_id')->constrained('shift_templates')->cascadeOnDelete();
            $table->date('session_date');         // scheduled start date of the shift
            $table->string('action', 20);          // clock_in, clock_out
            $table->string('status', 20)->default('open'); // open, closed

            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();

            $table->timestamp('closes_at')->nullable(); // auto-close time (UTC)
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 40)->nullable(); // manual, cutoff, superseded

            $table->timestamps();

            $table->unique(['shift_template_id', 'session_date', 'action']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
