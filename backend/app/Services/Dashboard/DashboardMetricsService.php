<?php

namespace App\Services\Dashboard;

use App\Events\DashboardMetricsUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the single payload that powers the dashboard charts.
 *
 * All aggregations come from the read-only database views created by the
 * `create_dashboard_analytics_views` migration, so the (potentially large)
 * GROUP BY work happens in the database rather than in PHP. The assembled
 * payload is cached; every write to a relevant model invalidates it and emits
 * a lightweight realtime signal so connected clients refetch.
 */
class DashboardMetricsService
{
    private const CACHE_KEY = 'dashboard:analytics';

    private const SIGNAL_THROTTLE_KEY = 'dashboard:analytics:broadcast';

    private const TREND_DAYS = 14;

    private const TREND_MONTHS = 6;

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        if (! config('reports.cache.enabled')) {
            return $this->build();
        }

        $ttl = (int) config('reports.cache.ttl', 300);

        return Cache::flexible(self::CACHE_KEY, [$ttl, $ttl + 60], fn (): array => $this->build());
    }

    /**
     * Drop the cached payload and nudge connected clients to refetch. The
     * broadcast itself is throttled so a burst of writes (e.g. dispatching a
     * prescription touches several rows) emits a single signal.
     */
    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);

        $last = Cache::get(self::SIGNAL_THROTTLE_KEY);

        if ($last !== null && (time() - (int) $last) < 2) {
            return;
        }

        Cache::put(self::SIGNAL_THROTTLE_KEY, time(), 10);

        event(new DashboardMetricsUpdated);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $trendStart = $today->subDays(self::TREND_DAYS - 1);
        $monthStart = $today->startOfMonth()->toDateString();

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'range' => ['days' => self::TREND_DAYS, 'months' => self::TREND_MONTHS],
            'appointments' => $this->appointments($trendStart, $today, $monthStart),
            'patients' => $this->patients($trendStart, $today, $monthStart),
            'revenue' => $this->revenue($today, $monthStart),
            'inventory' => $this->inventory($today),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointments(CarbonImmutable $trendStart, CarbonImmutable $today, string $monthStart): array
    {
        $rows = DB::table('vw_appointments_daily')
            ->where('day', '>=', $trendStart->toDateString())
            ->get();

        $totalsByDay = [];
        $statusTotals = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;
            $total = (int) $row->total;

            $totalsByDay[$day] = ($totalsByDay[$day] ?? 0) + $total;
            $statusTotals[$row->status] = ($statusTotals[$row->status] ?? 0) + $total;
        }

        $trend = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $date = $trendStart->addDays($i);
            $key = $date->toDateString();
            $trend[] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'total' => $totalsByDay[$key] ?? 0,
            ];
        }

        $byStatus = [];
        foreach ($statusTotals as $status => $total) {
            $byStatus[] = ['status' => $status, 'total' => $total];
        }

        return [
            'today' => $totalsByDay[$today->toDateString()] ?? 0,
            'this_month' => (int) DB::table('vw_appointments_daily')->where('day', '>=', $monthStart)->sum('total'),
            'total' => (int) DB::table('vw_appointments_daily')->sum('total'),
            'trend' => $trend,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patients(CarbonImmutable $trendStart, CarbonImmutable $today, string $monthStart): array
    {
        $byDay = DB::table('vw_patients_daily')
            ->where('day', '>=', $trendStart->toDateString())
            ->pluck('total', 'day');

        $trend = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $date = $trendStart->addDays($i);
            $key = $date->toDateString();
            $trend[] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'total' => (int) ($byDay[$key] ?? 0),
            ];
        }

        return [
            'total' => (int) DB::table('vw_patients_daily')->sum('total'),
            'new_this_month' => (int) DB::table('vw_patients_daily')->where('day', '>=', $monthStart)->sum('total'),
            'trend' => $trend,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenue(CarbonImmutable $today, string $monthStart): array
    {
        $firstMonth = $today->subMonths(self::TREND_MONTHS - 1)->startOfMonth();

        $byDay = DB::table('vw_revenue_daily')
            ->where('day', '>=', $firstMonth->toDateString())
            ->pluck('total', 'day');

        $byMonth = [];
        foreach ($byDay as $day => $total) {
            $month = substr((string) $day, 0, 7);
            $byMonth[$month] = ($byMonth[$month] ?? 0.0) + (float) $total;
        }

        $trend = [];
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $month = $firstMonth->addMonths($i);
            $key = $month->format('Y-m');
            $trend[] = [
                'month' => $key,
                'label' => $month->format('M'),
                'total' => round($byMonth[$key] ?? 0.0, 2),
            ];
        }

        return [
            'total' => round((float) DB::table('vw_revenue_daily')->sum('total'), 2),
            'this_month' => round((float) DB::table('vw_revenue_daily')->where('day', '>=', $monthStart)->sum('total'), 2),
            'trend' => $trend,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventory(CarbonImmutable $today): array
    {
        $stock = DB::table('vw_medicine_stock')->get();

        $active = 0;
        $lowStock = 0;

        foreach ($stock as $row) {
            if (! filter_var($row->is_active, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $active++;

            if ((int) $row->total_stock <= (int) $row->reorder_level) {
                $lowStock++;
            }
        }

        $expiring = DB::table('vw_inventory_expiring')
            ->whereBetween('expiration_date', [
                $today->toDateString(),
                $today->addDays(30)->toDateString(),
            ])
            ->count();

        return [
            'active_medicines' => $active,
            'low_stock' => $lowStock,
            'expiring_within_30_days' => $expiring,
        ];
    }
}
