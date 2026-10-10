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
        Schema::create('expert_rule_conditions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expert_rule_id')
                ->constrained('expert_rules')
                ->cascadeOnDelete();

            $table->foreignId('symptom_id')
                ->constrained('symptoms')
                ->restrictOnDelete();

            $table->string('operator', 20)->default('present');
            $table->boolean('expected_value')->default(true);
            $table->unsignedInteger('condition_group')->default(1);
            $table->timestamps();

            $table->unique([
                'expert_rule_id',
                'symptom_id',
                'condition_group',
            ], 'rule_condition_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expert_rule_conditions');
    }
};
