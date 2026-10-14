<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();

            $table->string('admission_number', 50)->unique();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('ward_id')
                ->nullable()
                ->constrained('wards')
                ->nullOnDelete();

            $table->foreignId('bed_id')
                ->nullable()
                ->constrained('beds')
                ->nullOnDelete();

            $table->foreignId('attending_doctor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 20)->default('admitted');
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();

            $table->text('reason')->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('discharged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('ward_id');
            $table->index('bed_id');
            $table->index('status');
            $table->index('admitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
