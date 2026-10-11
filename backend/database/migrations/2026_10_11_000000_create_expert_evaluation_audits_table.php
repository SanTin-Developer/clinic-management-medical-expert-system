<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in evaluation audit trail written by the Python expert system.
     *
     * Symptom IDs are stored as JSON text. Auditing is disabled by default in
     * the expert system; see EXPERT_AUDIT_ENABLED. This is an additive table and
     * does not alter the engine's knowledge-base schema.
     */
    public function up(): void
    {
        Schema::create('expert_evaluation_audits', function (Blueprint $table) {
            $table->id();
            $table->string('symptom_ids', 512)->nullable();
            $table->unsignedInteger('matched_rule_count')->default(0);
            $table->text('matched_rule_codes')->nullable();
            $table->string('engine_version', 32)->nullable();
            $table->boolean('is_clinically_validated')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_evaluation_audits');
    }
};
