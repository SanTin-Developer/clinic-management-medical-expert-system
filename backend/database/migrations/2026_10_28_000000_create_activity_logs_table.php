<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only user activity trail backing the sidebar "Audit Logs" page.
     *
     * Records who did what and when: model create/update/delete actions plus
     * authentication events (sign-in, sign-out, failed sign-in). This is a
     * general activity log and is intentionally separate from the expert
     * system evaluation audits, security events, and attendance/assignment
     * audit tables.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();
            $table->string('event', 50);
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
