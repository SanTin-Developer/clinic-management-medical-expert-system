<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->string('type', 20)->default('deposit'); // deposit, credit, adjustment
            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('applied_to_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 20)->default('available'); // available, applied, refunded
            $table->timestamps();
            $table->softDeletes();

            $table->index(['admission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_deposits');
    }
};
