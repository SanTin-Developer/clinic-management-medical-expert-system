<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('admission_id')
                ->nullable()
                ->constrained('admissions')
                ->nullOnDelete();

            $table->foreignId('assigned_nurse_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('task_type', 30);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');

            $table->timestamp('scheduled_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('admission_id');
            $table->index('assigned_nurse_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_tasks');
    }
};
