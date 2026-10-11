<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    //
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Consultation::class);

        $consultations = Consultation::with([
            'appointment',
            'patient',
            'doctor',
        ])
            ->latest()
            ->paginate(15);

        return response()->json($consultations);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Consultation::class);

        $validated = $request->validate([
            'appointment_id' => [
                'required',
                'integer',
                'exists:appointments,id',
            ],

            'patient_id' => [
                'required',
                'integer',
                'exists:patients,id',
            ],

            'doctor_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'chief_complaint' => [
                'nullable',
                'string',
            ],

            'symptoms' => [
                'nullable',
                'string',
            ],

            'clinical_notes' => [
                'nullable',
                'string',
            ],

            'examination' => [
                'nullable',
                'string',
            ],

            'assessment' => [
                'nullable',
                'string',
            ],

            'plan' => [
                'nullable',
                'string',
            ],
        ]);

        // Verify selected user is a Doctor.
        $doctor = User::query()
            ->role('Doctor')
            ->findOrFail($validated['doctor_id']);

        if (! $doctor) {
            return response()->json([
                'message' => 'Selected user is not a Doctor.',
            ], 422);
        }

        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], 404);
        }

        // Verify appointment belongs to selected patient.
        if ($appointment->patient_id !== (int) $validated['patient_id']) {
            return response()->json([
                'message' => 'Selected patient does not belongs to this appointment.',
            ], 422);
        }

        // Verify appointment belongs to selected doctor.
        if ($appointment->doctor_id !== (int) $validated['doctor_id']) {
            return response()->json([
                'message' => 'Selected doctor is not assigned to this appointment.',
            ], 422);
        }

        // Do not create consultation for cancelled/no-show appointments.
        if (in_array($appointment->status, ['cancelled', 'no-show'])) {
            return response()->json([
                'message' => 'Cannot create consultation for cancelled or no-show appointment.',
            ], 422);
        }

        // One consultation per appointment
        if (Consultation::where('appointment_id', $appointment->id)->exists()) {
            return response()->json([
                'message' => 'A consultation already exists for this appointment.',
            ], 422);
        }

        $consultation = Consultation::create($validated);

        return response()->json([
            'message' => 'Consultation created successfully.',
            'data' => $consultation->load([
                'appointment',
                'patient',
                'doctor',
            ]),
        ], 201);
    }

    public function show(Consultation $consultation): JsonResponse
    {
        $this->authorize('view', $consultation);

        return response()->json([
            'data' => $consultation->load([
                'appointment',
                'patient',
                'doctor',
            ]),
        ]);
    }

    public function update(Request $request, Consultation $consultation): JsonResponse
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate([
            'chief_complaint' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'symptoms' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'clinical_notes' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'examination' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'assessment' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'plan' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $consultation->update($validated);

        return response()->json([
            'message' => 'Consultation updated successfully.',
            'data' => $consultation->fresh()->load([
                'appointment',
                'patient',
                'doctor',
            ]),
        ]);
    }

    public function destroy(Consultation $consultation): JsonResponse
    {
        $this->authorize('delete', $consultation);

        $consultation->delete();

        return response()->json([
            'message' => 'Consultation deleted successfully.',
        ]);
    }
}
