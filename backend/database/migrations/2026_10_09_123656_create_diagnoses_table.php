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
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                ->constrained('consultations')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('diagnosis_name', 255);
            $table->text('description')->nullable();
            $table->string('severity', 30)->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('diagnosis_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
