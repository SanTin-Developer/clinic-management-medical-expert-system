<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deployment diagnostics for the optional demo dataset.
 *
 * The hosted free tier has no shell access, so when demo data is missing the
 * only way to tell "flag off" from "seeder failed" from "wrong database" is to
 * ask the API itself. This endpoint is LOCAL-ONLY: it is registered as a web
 * route and never under api.php, and it returns 404 outside the local
 * environment, so it cannot leak data on a public deployment.
 */
class SeedStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $counts = $this->counts();

        return response()->json([
            'app_env' => app()->environment(),
            'db_connection' => DB::connection()->getConfig('driver'),
            'db_database' => DB::connection()->getConfig('database'),
            'seed_demo_users' => filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL),
            'seed_demo_data' => filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL),
            'reference_data_marker' => Cache::store('database')->get('reference-data-seeded-v3'),
            'demo_data_marker' => Cache::store('database')->get('demo-data-seeded-v1'),
            'counts' => $counts,
            'diagnosis' => $this->diagnosis($counts),
        ]);
    }

    /**
     * Unauthenticated deployment probe. Reports only the two values needed to
     * tell "flag off" from "seeder failed", plus an overall verdict, so it is
     * safe to expose without credentials.
     */
    public function probe(): JsonResponse
    {
        $counts = $this->counts();

        return response()->json([
            'seed_demo_data' => filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL),
            'demo_data_marker' => Cache::store('database')->get('demo-data-seeded-v1'),
            'staff_count' => $counts['staff'] ?? null,
            'patient_count' => $counts['patients'] ?? null,
            'demo_data_present' => ($counts['staff'] ?? 0) >= 40,
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function counts(): array
    {
        $counts = [];

        foreach ([
            'staff' => 'staff',
            'staff_documents' => 'staff_documents',
            'staff_salary_histories' => 'staff_salary_histories',
            'attendance_records' => 'attendance_records',
            'shifts' => 'shifts',
            'leave_requests' => 'leave_requests',
            'announcements' => 'announcements',
            'system_alerts' => 'system_alerts',
            'activity_logs' => 'activity_logs',
            'conversations' => 'conversations',
            'pharmacy_sales' => 'pharmacy_sales',
            'billable_services' => 'billable_services',
            'admission_charges' => 'admission_charges',
            'admission_deposits' => 'admission_deposits',
            'clinic_holidays' => 'clinic_holidays',
            'expert_evaluation_audits' => 'expert_evaluation_audits',
            'staff_invitations' => 'staff_invitations',
            'contact_messages' => 'contact_messages',
            'media_assets' => 'media_assets',
            'patient_documents' => 'patient_documents',
            'patients' => 'patients',
            'appointments' => 'appointments',
            'invoices' => 'invoices',
            'payments' => 'payments',
            'users' => 'users',
            'medicines' => 'medicines',
            'inventory_items' => 'inventory_items',
        ] as $key => $table) {
            try {
                $counts[$key] = DB::table($table)->count();
            } catch (\Throwable $exception) {
                $counts[$key] = 'ERROR: '.$exception->getMessage();
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int|string>  $counts
     * @return array<string, string>
     */
    private function diagnosis(array $counts): array
    {
        $staff = (int) ($counts['staff'] ?? 0);
        $patients = (int) ($counts['patients'] ?? 0);
        $enabled = filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL);
        $marker = Cache::store('database')->get('demo-data-seeded-v1');

        if ($staff >= 40 && $patients >= 20) {
            return ['status' => 'ok', 'message' => 'Demo data is present.'];
        }

        if (! $enabled) {
            return [
                'status' => 'flag-off',
                'message' => 'SEED_DEMO_DATA is not enabled, so app:seed-demo-data skips on every boot.',
            ];
        }

        if ($marker !== null) {
            return [
                'status' => 'marker-set-but-empty',
                'message' => 'The demo-data marker was written on '.$marker.', but the data is missing — the seeder reported success without persisting rows. Check storage/logs/seed.log.',
            ];
        }

        return [
            'status' => 'not-run',
            'message' => 'SEED_DEMO_DATA is enabled but the seeder has not completed. It runs in the background on boot; check storage/logs/seed.log for errors.',
        ];
    }
}
