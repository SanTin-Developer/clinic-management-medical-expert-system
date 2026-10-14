<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    //
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $query = MedicalRecord::with([
            'patient',
            'appointment',
            'consultation',
            'doctor',
        ]);
        $base = clone $query;

        $records = $query->latest()->paginate(15);

        $stats = [
            'total' => $records->total(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($records->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MedicalRecord::class);

        $validated = $request->validate([
            'patient_id' => [
                'required',
                'integer',
                'exists:patients,id',
            ],

            'appointment_id' => [
                'nullable',
                'integer',
                'exists:appointments,id',
            ],

            'consultation_id' => [
                'nullable',
                'integer',
                'exists:consultations,id',
            ],

            'medical_history' => [
                'nullable',
                'string',
            ],

            'allergies' => [
                'nullable',
                'string',
            ],

            'treatment_plan' => [
                'nullable',
                'string',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $appointment = null;
        $consultation = null;

        if (! empty($validated['appointment_id'])) {
            $appointment = Appointment::findOrFail(
                $validated['appointment_id']
            );

            if ((int) $appointment->patient_id !== (int) $validated['patient_id']) {
                throw ValidationException::withMessages([
                    'appointment_id' => [
                        'The appointment does not belong to this patient.',
                    ],
                ]);
            }
        }

        if (! empty($validated['consultation_id'])) {
            $consultation = Consultation::with('appointment')
                ->findOrFail($validated['consultation_id']);

            if ((int) $consultation->patient_id !== (int) $validated['patient_id']) {
                throw ValidationException::withMessages([
                    'consultation_id' => [
                        'The consultation does not belong to this patient.',
                    ],
                ]);
            }

            if (
                $appointment &&
                (int) $consultation->appointment_id !== (int) $appointment->id
            ) {
                throw ValidationException::withMessages([
                    'appointment_id' => [
                        'The appointment does not match the consultation.',
                    ],
                ]);
            }

            if (
                $request->user()->hasRole('Doctor') && (int) $consultation->doctor_id !== (int) $request->user()->id
            ) {
                return response()->json([
                    'message' => 'You are not assigned to this consultation.',
                ], 403);
            }

            // If an appointment wasn't explicitly supplied, drive it
            // from the consultation to keep the relationships consistent.
            $validated['appointment_id'] ??= $consultation->appointment_id;
        }

        $doctor = $request->user();

        if (! $doctor->hasRole('Doctor')) {
            $doctor = User::query()
                ->role('Doctor')
                ->find($request->input('doctor_id'));
        }

        if (! $doctor) {
            return response()->json([
                'message' => 'A valid doctor must be assigned to the medical record.',
            ], 422);
        }

        $record = MedicalRecord::create([
            'patient_id' => $validated['patient_id'],
            'appointment_id' => $validated['appointment_id'] ?? null,
            'consultation_id' => $validated['consultation_id'] ?? null,
            'doctor_id' => $doctor->id,
            'medical_history' => $validated['medical_history'] ?? null,
            'allergies' => $validated['allergies'] ?? null,
            'treatment_plan' => $validated['treatment_plan'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Medical record created successfully.',
            'data' => $record->load([
                'patient',
                'appointment',
                'consultation',
                'doctor',
            ]),
        ], 201);
    }

    public function show(MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('view', $medicalRecord);

        return response()->json([
            'data' => $medicalRecord->load([
                'patient',
                'appointment',
                'consultation',
                'doctor',
            ]),
        ]);
    }

    public function update(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('update', $medicalRecord);

        $validated = $request->validate([
            'medical_history' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'allergies' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'treatment_plan' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $medicalRecord->update($validated);

        return response()->json([
            'message' => 'Medical record updated successfully.',
            'data' => $medicalRecord->fresh()->load([
                'patient',
                'appointment',
                'consultation',
                'doctor',
            ]),
        ]);
    }

    public function destroy(MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('delete', $medicalRecord);

        $medicalRecord->delete();

        return response()->json([
            'message' => 'Medical record deleted successfully.',
        ]);
    }
}
