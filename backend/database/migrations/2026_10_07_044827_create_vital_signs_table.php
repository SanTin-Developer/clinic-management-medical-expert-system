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
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('nurse_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('temperature', 4, 1)->nullable();

            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();

            $table->decimal('oxygen_saturation', 5, 2)->nullable();

            $table->decimal('weight', 5, 2);
            $table->decimal('height', 5, 2);

            $table->text('symptoms')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('appointment_id');
            $table->index('patient_id');
            $table->index('nurse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
