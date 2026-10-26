<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregate views that power the dashboard analytics endpoint.
 *
 * The SQL is intentionally portable across PostgreSQL (production) and SQLite
 * (the test suite): only ANSI aggregates, `date()` and `count/sum/group by`
 * are used, and soft-deleted clinical rows are excluded explicitly.
 */
return new class extends Migration
{
    private array $views = [
        'vw_appointments_daily',
        'vw_patients_daily',
        'vw_revenue_daily',
        'vw_medicine_stock',
        'vw_inventory_expiring',
    ];

    public function up(): void
    {
        foreach ($this->views as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }

        // Non-deleted appointments grouped by day and status. The day is
        // normalised to YYYY-MM-DD: PostgreSQL stores a `date` column, but the
        // SQLite test database keeps Eloquent's full datetime string.
        DB::statement(<<<'SQL'
            CREATE VIEW vw_appointments_daily AS
            SELECT substr(CAST(appointment_date AS VARCHAR), 1, 10) AS day, status, COUNT(*) AS total
            FROM appointments
            WHERE deleted_at IS NULL
            GROUP BY substr(CAST(appointment_date AS VARCHAR), 1, 10), status
        SQL);

        // Non-deleted patients grouped by registration day.
        DB::statement(<<<'SQL'
            CREATE VIEW vw_patients_daily AS
            SELECT date(created_at) AS day, COUNT(*) AS total
            FROM patients
            WHERE deleted_at IS NULL
            GROUP BY date(created_at)
        SQL);

        // Recorded payments grouped by settlement day.
        DB::statement(<<<'SQL'
            CREATE VIEW vw_revenue_daily AS
            SELECT date(paid_at) AS day, SUM(amount) AS total
            FROM payments
            WHERE paid_at IS NOT NULL
            GROUP BY date(paid_at)
        SQL);

        // Total on-hand stock per medicine, with its reorder threshold.
        DB::statement(<<<'SQL'
            CREATE VIEW vw_medicine_stock AS
            SELECT m.id AS medicine_id,
                   m.reorder_level AS reorder_level,
                   m.is_active AS is_active,
                   COALESCE(SUM(ii.quantity), 0) AS total_stock
            FROM medicines m
            LEFT JOIN inventory_items ii ON ii.medicine_id = m.id
            GROUP BY m.id, m.reorder_level, m.is_active
        SQL);

        // Positive-quantity batches that carry an expiry date.
        DB::statement(<<<'SQL'
            CREATE VIEW vw_inventory_expiring AS
            SELECT ii.id AS id,
                   ii.medicine_id AS medicine_id,
                   ii.expiration_date AS expiration_date,
                   ii.quantity AS quantity
            FROM inventory_items ii
            WHERE ii.quantity > 0 AND ii.expiration_date IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        foreach ($this->views as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }
    }
};
