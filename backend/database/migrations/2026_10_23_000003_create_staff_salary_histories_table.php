<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only salary change history. Every change to a staff member's
 * compensation writes a row recording the new amount, who changed it, when,
 * and an optional note. Access is protected by the staff.salary.* permissions
 * and each change is mirrored into the security_events audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_salary_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('staff_id')
                ->constrained('staff')
                ->cascadeOnDelete();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('pay_frequency', 20)->nullable();
            $table->decimal('allowances', 12, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salary_histories');
    }
};
