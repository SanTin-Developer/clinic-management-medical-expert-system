<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        //
        $this->authorize('viewAny', Appointment::class);

        $appointments = Appointment::with(['patient', 'doctor'])
            ->latest('appointment_date')
            ->latest('appointment_time')
            ->paginate(15);

        return response()->json($appointments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        //
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
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
            'appointment_date' => [
                'required',
                'date',
            ],
            'appointment_time' => [

                'required',
                'date_format:H:i',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'scheduled',
                    'confirmed',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'no_show',
                ]),
            ],
            'note' => [
                'nullable',
                'string',
            ],
        ]);

        $doctor = User::query()
            ->role('Doctor')
            ->findOrFail($validated['doctor_id']);

        if (! $doctor) {
            return response()->json([
                'message' => 'The selected doctor is not a valid doctor.',
            ], 422);
        }

        $alreadyBooked = Appointment::query()
            ->where('doctor_id', $validated['doctor_id'])
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->where('appointment_time', $validated['appointment_time'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'message' => 'Sorry, Doctor already has an appointment for the specified date and time.',
            ], 422);
        }

        $validated['appointment_number'] = $this->generateAppointmentNumber();
        $validated['status'] ??= 'scheduled';

        $appointment = Appointment::create($validated);

        return response()->json([
            'message' => 'Appointment created successfully.',
            'appointment' => $appointment->load(['patient', 'doctor']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return response()->json([
            'appointment' => $appointment->load(['patient', 'doctor']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        //
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'patient_id' => [
                'sometimes',
                'integer',
                'exists:patients,id',
            ],
            'doctor_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],
            'appointment_date' => [
                'sometimes',
                'date',
            ],
            'appointment_time' => [
                'sometimes',
                'date_format:H:i',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'scheduled',
                    'confirmed',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'no_show',
                ]),
            ],
            'note' => [
                'nullable',
                'string',
            ],
        ]);

        $doctorId = $validated['doctor_id'] ?? $appointment->doctor_id;
        $date = $validated['appointment_date']
            ?? $appointment->appointment_date->format('Y-m-d');
        $time = $validated['appointment_time']
            ?? $appointment->appointment_time;

        $doctor = User::query()
            ->role('Doctor')
            ->findOrFail($doctorId);

        if (! $doctor) {
            return response()->json([
                'message' => 'The selected doctor is not a valid doctor.',
            ], 422);
        }

        $alreadyBooked = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->where('appointment_time', $time)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('id', '!=', $appointment->id)
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'message' => 'Sorry, Doctor already has an appointment for the specified date and time.',
            ], 422);
        }

        $appointment->update($validated);

        return response()->json([
            'message' => 'Appointment updated successfully.',
            'appointment' => $appointment->load(['patient', 'doctor']),
        ]);
    }

    /**
     * Cancel the specified resource in storage.
     */
    public function cancel(Appointment $appointment): JsonResponse
    {
        //
        $this->authorize('cancel', $appointment);

        if ($appointment->status === 'cancelled') {
            return response()->json([
                'message' => 'Appointment is already cancelled.',
            ], 422);
        }

        $appointment->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Appointment cancelled successfully.',
            'appointment' => $appointment->load(['patient', 'doctor']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return response()->json([
            'message' => 'Appointment deleted successfully.',
        ]);
    }

    /**
     * Generate a unique appointment number.
     */
    private function generateAppointmentNumber(): string
    {
        do {
            $number = 'APT-'.random_int(100000, 999999);
        } while (Appointment::where('appointment_number', $number)->exists());

        return $number;
    }
}
