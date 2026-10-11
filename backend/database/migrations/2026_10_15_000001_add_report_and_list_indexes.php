<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hot-path indexes for list ordering and report aggregation queries.
     *
     * - patients.created_at: "new patients this month" count + list ordering.
     * - appointments(appointment_date, status): date-range report filters,
     *   which previously fell back to a sequential scan because existing
     *   indexes lead with doctor_id / patient_id.
     * - invoices(status, issued_at): unpaid/partial balance lookups and
     *   date-scoped billing views.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->index('created_at', 'patients_created_at_index');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['appointment_date', 'status'], 'appointments_appointment_date_status_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'issued_at'], 'invoices_status_issued_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasIndex('patients', 'patients_created_at_index')) {
                $table->dropIndex('patients_created_at_index');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasIndex('appointments', 'appointments_appointment_date_status_index')) {
                $table->dropIndex('appointments_appointment_date_status_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasIndex('invoices', 'invoices_status_issued_at_index')) {
                $table->dropIndex('invoices_status_issued_at_index');
            }
        });
    }
};
