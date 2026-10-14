<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    private const LIMIT_PER_GROUP = 5;

    /**
     * Search across every record type the authenticated user is permitted to view.
     */
    public function index(Request $request, PatientAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $term = trim($validated['q']);
        $user = $request->user();

        // A restricted nurse may only ever find patients they are assigned to.
        $scopedPatientIds = $access->isRestricted($user)
            ? $access->assignedPatientIds($user)
            : null;

        $groups = [];

        if ($user->can('viewAny', Patient::class)) {
            $groups[] = [
                'type' => 'patients',
                'label' => 'Patients',
                'items' => $this->patients($term, $scopedPatientIds),
            ];
        }

        if ($user->can('viewAny', Appointment::class)) {
            $groups[] = [
                'type' => 'appointments',
                'label' => 'Appointments',
                'items' => $this->appointments($term, $scopedPatientIds),
            ];
        }

        if ($user->can('viewAny', Prescription::class)) {
            $groups[] = [
                'type' => 'prescriptions',
                'label' => 'Prescriptions',
                'items' => $this->prescriptions($term, $scopedPatientIds),
            ];
        }

        if ($user->can('viewAny', Invoice::class)) {
            $groups[] = [
                'type' => 'invoices',
                'label' => 'Invoices',
                'items' => $this->invoices($term),
            ];
        }

        if ($user->can('viewAny', Medicine::class)) {
            $groups[] = [
                'type' => 'medicines',
                'label' => 'Medicines',
                'items' => $this->medicines($term),
            ];
        }

        return response()->json([
            'query' => $term,
            'groups' => $groups,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function patients(string $term, ?Collection $scopedPatientIds = null): array
    {
        return Patient::query()
            ->when($scopedPatientIds !== null, fn (Builder $query) => $query->whereIn('id', $scopedPatientIds))
            ->where(function (Builder $query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('patient_number', 'like', "%{$term}%")
                    ->orWhere('phone_number', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'title' => trim("{$patient->first_name} {$patient->last_name}"),
                'subtitle' => $patient->patient_number,
                'url' => "/patients/{$patient->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appointments(string $term, ?Collection $scopedPatientIds = null): array
    {
        return Appointment::query()
            ->with('patient')
            ->when($scopedPatientIds !== null, fn (Builder $query) => $query->whereIn('patient_id', $scopedPatientIds))
            ->where(function (Builder $query) use ($term) {
                $query->where('appointment_number', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%")
                    ->orWhere('status', 'like', "%{$term}%")
                    ->orWhereHas('patient', function (Builder $patient) use ($term) {
                        $patient->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            })
            ->latest('appointment_date')
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'title' => $appointment->appointment_number,
                'subtitle' => $this->subtitle($this->patientName($appointment), $appointment->status),
                'url' => "/appointments/{$appointment->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prescriptions(string $term, ?Collection $scopedPatientIds = null): array
    {
        return Prescription::query()
            ->with('patient')
            ->when($scopedPatientIds !== null, fn (Builder $query) => $query->whereIn('patient_id', $scopedPatientIds))
            ->where(function (Builder $query) use ($term) {
                $query->where('prescription_number', 'like', "%{$term}%")
                    ->orWhere('status', 'like', "%{$term}%")
                    ->orWhereHas('patient', function (Builder $patient) use ($term) {
                        $patient->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            })
            ->latest()
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Prescription $prescription) => [
                'id' => $prescription->id,
                'title' => $prescription->prescription_number,
                'subtitle' => $this->subtitle($this->patientName($prescription), $prescription->status),
                'url' => "/prescriptions/{$prescription->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoices(string $term): array
    {
        return Invoice::query()
            ->with('patient')
            ->where(function (Builder $query) use ($term) {
                $query->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('status', 'like', "%{$term}%")
                    ->orWhereHas('patient', function (Builder $patient) use ($term) {
                        $patient->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            })
            ->latest()
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'title' => $invoice->invoice_number,
                'subtitle' => $this->subtitle($this->patientName($invoice), $invoice->status),
                'url' => "/billing/{$invoice->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function medicines(string $term): array
    {
        return Medicine::query()
            ->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Medicine $medicine) => [
                'id' => $medicine->id,
                'title' => $medicine->name,
                'subtitle' => $medicine->code,
                'url' => '/pharmacy',
            ])
            ->all();
    }

    private function patientName(Appointment|Prescription|Invoice $record): string
    {
        $patient = $record->relationLoaded('patient') ? $record->patient : null;

        if (! $patient) {
            return 'Unknown patient';
        }

        return trim("{$patient->first_name} {$patient->last_name}");
    }

    private function subtitle(string $name, ?string $status): string
    {
        return $status ? "{$name} · {$status}" : $name;
    }
}
