<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    // Display a paginated list of patients
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->latest()
            ->paginate(15);

        return response()->json($patients);
    }

    // Create new patient
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Patient::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'blood_type' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'allergies' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],

        ]);

        $validated['patient_number'] = $this->generatePatientNumber();

        $patient = Patient::create($validated);

        return response()->json([
            'message' => 'Patient created successfully',
            'data' => $patient,
        ], 201);
    }

    // Show a specific patient
    public function show(Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        return response()->json([
            'patient' => $patient,
        ]);
    }

    // Update an existing patient
    public function update(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('update', $patient);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'phone_number' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'blood_type' => ['sometimes', 'nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'allergies' => ['sometimes', 'nullable', 'string'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $patient->update($validated);

        return response()->json([
            'message' => 'Patient updated successfully',
            'data' => $patient,
        ]);
    }

    // Delete a patient
    public function destroy(Patient $patient): JsonResponse
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully',
        ]);
    }

    // Generate a unique patient number
    private function generatePatientNumber(): string
    {
        do {
            $number = 'PAT-'.random_int(100000, 999999);
        } while (Patient::where('patient_number', $number)->exists());

        return $number;
    }
}
