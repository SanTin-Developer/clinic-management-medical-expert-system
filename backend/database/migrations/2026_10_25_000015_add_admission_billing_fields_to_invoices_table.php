<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->after('consultation_id')->constrained('admissions')->nullOnDelete();
            $table->string('invoice_type', 20)->default('outpatient')->after('status'); // outpatient, inpatient_daily, inpatient_discharge
            $table->date('billing_period_start')->nullable()->after('issued_at');
            $table->date('billing_period_end')->nullable()->after('billing_period_start');
            $table->foreignId('billing_run_id')->nullable()->after('created_by');
            $table->index(['admission_id', 'invoice_type']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['admission_id', 'invoice_type']);
            $table->dropForeign(['admission_id']);
            $table->dropForeign(['billing_run_id']);
            $table->dropColumn(['admission_id', 'invoice_type', 'billing_period_start', 'billing_period_end', 'billing_run_id']);
        });
    }
};
