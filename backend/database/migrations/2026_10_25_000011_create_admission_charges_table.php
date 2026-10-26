<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->date('charge_date');
            $table->time('charge_time')->nullable();
            $table->string('category', 30); // room, medicine, service, supply, nursing, other
            $table->text('description');
            $table->foreignId('billable_service_id')->nullable()->constrained('billable_services')->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('source_type', 40)->nullable(); // admission, transfer, medication_administration, nursing_task, prescription_item, billable_service_order, room_daily, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->text('discount_reason')->nullable();
            $table->foreignId('discounted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('unbilled'); // unbilled, invoiced, voided, credited
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('invoiced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['admission_id', 'status', 'charge_date']);
            $table->index(['invoice_id']);
            $table->index(['source_type', 'source_id']);
        });

        // Partial unique index to prevent duplicate charges from same source (Postgres)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX admission_charges_unique_source ON admission_charges (source_type, source_id) WHERE source_type IS NOT NULL AND source_id IS NOT NULL AND status != 'voided' AND deleted_at IS NULL");
        } else {
            // SQLite fallback - application level enforcement
            Schema::table('admission_charges', function (Blueprint $table) {
                $table->unique(['source_type', 'source_id'], 'admission_charges_source_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS admission_charges_unique_source');
        }
        Schema::dropIfExists('admission_charges');
    }
};
