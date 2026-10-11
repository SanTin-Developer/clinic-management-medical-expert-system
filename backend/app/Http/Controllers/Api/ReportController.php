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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $monthStart = now()->startOfMonth();

        $appointmentsByStatus = Appointment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

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

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byDoctor = (clone $query)
            ->selectRaw('doctor_id, count(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id');

        return response()->json([
            'data' => [
                'total' => (clone $query)->count(),
                'by_status' => $byStatus,
                'by_doctor_id' => $byDoctor,
            ],
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

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

        $payments = (clone $query)->get(['amount', 'method', 'paid_at']);

        $byDay = $payments
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->toDateString())
            ->map(fn ($group) => round((float) $group->sum('amount'), 2));

        $byMethod = $payments
            ->groupBy('method')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2));

        return response()->json([
            'data' => [
                'total' => round((float) $payments->sum('amount'), 2),
                'by_day' => $byDay,
                'by_method' => $byMethod,
                'outstanding_balance' => round((float) Invoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance'), 2),
            ],
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $lowStock = $this->lowStockMedicines();

        $stockValue = Medicine::withSum('inventoryItems as total_stock', 'quantity')
            ->get()
            ->sum(fn (Medicine $medicine) => (int) $medicine->total_stock * (float) $medicine->price);

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $this->authorizeReports($request);

        [$filename, $rows] = match ($report) {
            'appointments' => $this->appointmentsExport($request),
            'revenue' => $this->revenueExport($request),
            'inventory' => $this->inventoryExport(),
            default => abort(404, 'Unknown report.'),
        };

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

        $payments = (clone $query)->get(['amount', 'method', 'paid_at']);

        $rows = [['Total', 'Revenue', round((float) $payments->sum('amount'), 2)]];

        $byDay = $payments->groupBy(fn (Payment $payment) => $payment->paid_at?->toDateString());

        foreach ($byDay as $day => $group) {
            $rows[] = ['By day', $day, round((float) $group->sum('amount'), 2)];
        }

        foreach ($payments->groupBy('method') as $method => $group) {
            $rows[] = ['By method', $method, round((float) $group->sum('amount'), 2)];
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
