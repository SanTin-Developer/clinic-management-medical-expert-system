<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nurse_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // A single assignment grants access to every patient admitted to a
            // ward (ward_id) OR to one specific patient (patient_id). Exactly one
            // of the two is expected; the application layer enforces this.
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('ward_id')
                ->nullable()
                ->constrained('wards')
                ->cascadeOnDelete();

            $table->foreignId('admission_id')
                ->nullable()
                ->constrained('admissions')
                ->nullOnDelete();

            $table->string('shift', 20)->nullable();
            $table->string('status', 20)->default('active');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('ended_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('ended_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('nurse_id');
            $table->index('patient_id');
            $table->index('ward_id');
            $table->index('status');
            $table->index(['nurse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_assignments');
    }
};
