<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('admission_id')
                ->nullable()
                ->constrained('admissions')
                ->nullOnDelete();

            // Order source. Recording an administration NEVER mutates pharmacy
            // stock — dispensing already handles inventory.
            $table->foreignId('prescription_id')
                ->nullable()
                ->constrained('prescriptions')
                ->nullOnDelete();

            $table->foreignId('prescription_item_id')
                ->nullable()
                ->constrained('prescription_items')
                ->nullOnDelete();

            $table->foreignId('medicine_id')
                ->nullable()
                ->constrained('medicines')
                ->nullOnDelete();

            $table->foreignId('ordered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('administered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('dose')->nullable();
            $table->string('route', 30)->nullable();

            $table->timestamp('scheduled_at');
            $table->timestamp('administered_at')->nullable();

            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('admission_id');
            $table->index('prescription_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};
