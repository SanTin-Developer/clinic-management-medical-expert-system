<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const REPORT_TITLES = [
        'appointments' => 'Appointments Report',
        'revenue' => 'Revenue Report',
        'inventory' => 'Inventory Report',
    ];

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $this->cachedReport('report:summary', fn (): array => $this->summaryData()),
        ]);
    }

    private function summaryData(): array
    {
        $monthStart = now()->startOfMonth();

        $appointmentsByStatus = Appointment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'patients' => [
                'total' => Patient::count(),
                'new_this_month' => Patient::where('created_at', '>=', $monthStart)->count(),
            ],
            'appointments' => [
                'total' => Appointment::count(),
                'by_status' => $appointmentsByStatus,
            ],
            'consultations' => [
                'total' => Consultation::count(),
            ],
            'diagnoses' => [
                'total' => Diagnosis::count(),
            ],
            'prescriptions' => [
                'total' => Prescription::count(),
                'pending' => Prescription::where('status', 'pending')->count(),
            ],
            'billing' => [
                'invoices_total' => Invoice::count(),
                'unpaid' => Invoice::whereIn('status', ['unpaid', 'partially_paid'])->count(),
                'outstanding_balance' => round((float) Invoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance'), 2),
                'revenue_total' => round((float) Payment::sum('amount'), 2),
                'revenue_this_month' => round((float) Payment::where('paid_at', '>=', $monthStart)->sum('amount'), 2),
            ],
            'inventory' => [
                'medicines_total' => Medicine::count(),
                'active_medicines' => Medicine::where('is_active', true)->count(),
                'low_stock' => $this->lowStockMedicines()->count(),
                'expiring_within_30_days' => $this->expiringSoon()->count(),
            ],
        ];
    }

    public function appointments(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'data' => $this->cachedReport(
                'report:appointments:'.($validated['from'] ?? 'all').'|'.($validated['to'] ?? 'all'),
                fn (): array => $this->appointmentsData($validated)
            ),
        ]);
    }

    /**
     * @param  array{from?: string, to?: string}  $validated
     */
    private function appointmentsData(array $validated): array
    {
        $query = Appointment::query();

        if (isset($validated['from'])) {
            $query->whereDate('appointment_date', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('appointment_date', '<=', $validated['to']);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byDoctor = (clone $query)
            ->selectRaw('doctor_id, count(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id');

        return [
            'total' => (clone $query)->count(),
            'by_status' => $byStatus,
            'by_doctor_id' => $byDoctor,
        ];
    }

    public function revenue(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'data' => $this->cachedReport(
                'report:revenue:'.($validated['from'] ?? 'all').'|'.($validated['to'] ?? 'all'),
                fn (): array => $this->revenueData($validated)
            ),
        ]);
    }

    /**
     * @param  array{from?: string, to?: string}  $validated
     */
    private function revenueData(array $validated): array
    {
        $query = Payment::query();

        if (isset($validated['from'])) {
            $query->where('paid_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('paid_at', '<=', $validated['to'].' 23:59:59');
        }

        $byDay = (clone $query)
            ->selectRaw('date(paid_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $amount): float => round((float) $amount, 2));

        $byMethod = (clone $query)
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn (mixed $amount): float => round((float) $amount, 2));

        return [
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'by_day' => $byDay,
            'by_method' => $byMethod,
            'outstanding_balance' => round((float) Invoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance'), 2),
        ];
    }

    public function inventory(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $this->cachedReport('report:inventory', fn (): array => $this->inventoryData()),
        ]);
    }

    private function inventoryData(): array
    {
        $lowStock = $this->lowStockMedicines();

        $stockValue = Medicine::withSum('inventoryItems as total_stock', 'quantity')
            ->get()
            ->sum(fn (Medicine $medicine) => (int) $medicine->total_stock * (float) $medicine->price);

        return [
            'total_stock_value' => round((float) $stockValue, 2),
            'low_stock' => $lowStock->map(fn (Medicine $medicine) => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'code' => $medicine->code,
                'stock' => (int) $medicine->total_stock,
                'reorder_level' => (int) $medicine->reorder_level,
            ])->values(),
            'expiring_within_30_days' => $this->expiringSoon()->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'medicine_id' => $item->medicine_id,
                'batch_number' => $item->batch_number,
                'quantity' => (int) $item->quantity,
                'expiration_date' => $item->expiration_date?->toDateString(),
            ])->values(),
        ];
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $this->authorizeReports($request);

        [$filename, $rows] = $this->reportRows($request, $report);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Section', 'Label', 'Value'], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function pdf(Request $request, string $report): Response
    {
        $this->authorizeReports($request);

        [, $rows] = $this->reportRows($request, $report);

        $title = self::REPORT_TITLES[$report];

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $title,
            'rows' => $rows,
            'generatedAt' => now()->toDateTimeString(),
            'generatedBy' => $request->user()?->name ?? 'System',
        ])->setPaper('a4');

        return $pdf->download(Str::slug($title).'.pdf');
    }

    private function reportRows(Request $request, string $report): array
    {
        return match ($report) {
            'appointments' => $this->appointmentsExport($request),
            'revenue' => $this->revenueExport($request),
            'inventory' => $this->inventoryExport(),
            default => abort(404, 'Unknown report.'),
        };
    }

    /**
     * Cache aggregate report payloads so dashboard queries are not recomputed
     * on every request. Falls back to computing directly when caching is off.
     * Uses a stale-within grace period so that when the cache expires, a single
     * request refreshes it and every other request keeps serving the stale
     * value (avoids a thundering herd on the database).
     */
    private function cachedReport(string $key, Closure $callback): mixed
    {
        if (! config('reports.cache.enabled')) {
            return $callback();
        }

        $ttl = (int) config('reports.cache.ttl', 300);

        return Cache::flexible($key, [$ttl, $ttl + 60], $callback);
    }

    private function appointmentsExport(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Appointment::query();

        if (isset($validated['from'])) {
            $query->whereDate('appointment_date', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('appointment_date', '<=', $validated['to']);
        }

        $rows = [['Total', 'All appointments', (clone $query)->count()]];

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($byStatus as $status => $total) {
            $rows[] = ['By status', $status, $total];
        }

        $byDoctor = (clone $query)
            ->selectRaw('doctor_id, count(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id');

        foreach ($byDoctor as $doctorId => $total) {
            $rows[] = ['By doctor', $doctorId, $total];
        }

        return ['appointments-report.csv', $rows];
    }

    private function revenueExport(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Payment::query();

        if (isset($validated['from'])) {
            $query->where('paid_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('paid_at', '<=', $validated['to'].' 23:59:59');
        }

        $rows = [['Total', 'Revenue', round((float) (clone $query)->sum('amount'), 2)]];

        $byDay = (clone $query)
            ->selectRaw('date(paid_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->each(fn (Payment $payment) => $rows[] = ['By day', (string) $payment->day, round((float) $payment->total, 2)]);

        $byMethod = (clone $query)
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->get();

        foreach ($byMethod as $row) {
            $rows[] = ['By method', $row->method, round((float) $row->total, 2)];
        }

        $rows[] = [
            'Outstanding',
            'Balance',
            round((float) Invoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance'), 2),
        ];

        return ['revenue-report.csv', $rows];
    }

    private function inventoryExport(): array
    {
        $stockValue = Medicine::withSum('inventoryItems as total_stock', 'quantity')
            ->get()
            ->sum(fn (Medicine $medicine) => (int) $medicine->total_stock * (float) $medicine->price);

        $rows = [['Total', 'Stock value', round((float) $stockValue, 2)]];

        foreach ($this->lowStockMedicines() as $medicine) {
            $rows[] = ['Low stock', $medicine->name, (int) $medicine->total_stock];
        }

        foreach ($this->expiringSoon() as $item) {
            $rows[] = [
                'Expiring within 30 days',
                $item->medicine?->name ?? '',
                $item->expiration_date?->toDateString(),
            ];
        }

        return ['inventory-report.csv', $rows];
    }

    private function lowStockMedicines()
    {
        return Medicine::withSum('inventoryItems as total_stock', 'quantity')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Medicine $medicine) => (int) $medicine->total_stock <= (int) $medicine->reorder_level)
            ->values();
    }

    private function expiringSoon()
    {
        return InventoryItem::with('medicine')
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', now())
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->get();
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless($request->user()->can('reports.view'), 403, 'This action is unauthorized.');
    }
}
