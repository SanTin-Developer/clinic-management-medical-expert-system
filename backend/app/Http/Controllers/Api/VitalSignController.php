<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitalSignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        //
        $this->authorize('viewAny', VitalSign::class);

        $vitalSigns = VitalSign::with([
            'patient',
            'appointment',
            'nurse',
        ])
            ->latest()
            ->paginate(15);

        return response()->json($vitalSigns);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        //
        $this->authorize('create', VitalSign::class);

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

            'nurse_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'temperature' => [
                'nullable',
                'numeric',
                'between:30,45',
            ],

            'blood_pressure_systolic' => [
                'nullable',
                'integer',
                'between:50,250',
            ],

            'blood_pressure_diastolic' => [
                'nullable',
                'integer',
                'between:30,150',
            ],

            'heart_rate' => [
                'nullable',
                'integer',
                'between:30,250',
            ],

            'respiratory_rate' => [
                'nullable',
                'integer',
                'between:5,60',
            ],

            'oxygen_saturation' => [
                'nullable',
                'numeric',
                'between:50,100',
            ],

            'weight' => [
                'nullable',
                'numeric',
                'between:1,500',
            ],

            'height' => [
                'nullable',
                'numeric',
                'between:30,250',
            ],

            'symptoms' => [
                'nullable',
                'string',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $nurse = User::query()
            ->role('Nurse')
            ->findOrFail($validated['nurse_id']);

        if (! $nurse) {
            return response()->json([
                'message' => 'Selected user is not a Nurse.',
            ], 422);
        }

        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], 404);
        }

        if ($appointment->patient_id !== (int) $validated['patient_id']) {
            return response()->json([
                'message' => 'Selected patient does not belong to this appointment.',
            ], 422);
        }

        if (in_array($appointment->status, ['cancelled', 'no_show'])) {
            return response()->json([
                'message' => 'Cannot Record vital signs for a cancelled or no-show appointment.',
            ], 422);
        }

        $vitalSign = VitalSign::create($validated);

        return response()->json([
            'message' => 'Vital signs recorded successfully.',
            'data' => $vitalSign->load([
                'patient',
                'appointment',
                'nurse',
            ]),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(VitalSign $vitalSign): JsonResponse
    {
        //
        $this->authorize('view', $vitalSign);

        return response()->json([
            'data' => $vitalSign->load([
                'patient',
                'appointment',
                'nurse',
            ]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VitalSign $vitalSign): JsonResponse
    {
        //
        $this->authorize('update', $vitalSign);

        $validated = $request->validate([
            'temperature' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:30,45',
            ],

            'blood_pressure_systolic' => [
                'sometimes',
                'nullable',
                'integer',
                'between:50,250',
            ],

            'blood_pressure_diastolic' => [
                'sometimes',
                'nullable',
                'integer',
                'between:30,150',
            ],

            'heart_rate' => [
                'sometimes',
                'nullable',
                'integer',
                'between:30,250',
            ],

            'respiratory_rate' => [
                'sometimes',
                'nullable',
                'integer',
                'between:5,60',
            ],

            'oxygen_saturation' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:50,100',
            ],

            'weight' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:1,500',
            ],

            'height' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:30,250',
            ],

            'symptoms' => [
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

        $vitalSign->update($validated);

        return response()->json([
            'message' => 'Vital signs updated successfully.',
            'data' => $vitalSign->fresh()->load([
                'patient',
                'appointment',
                'nurse',
            ]),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VitalSign $vitalSign): JsonResponse
    {
        $this->authorize('delete', $vitalSign);

        $vitalSign->delete();

        return response()->json([
            'message' => 'Vital signs deleted successfully.',
        ]);
    }
}
