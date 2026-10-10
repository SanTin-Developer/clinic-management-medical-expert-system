<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Diagnosis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosisController extends Controller
{
    //
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Diagnosis::class);

        $diagnoses = Diagnosis::with([
            'consultation',
            'patient',
            'doctor',
        ])
            ->latest()
            ->paginate(15);

        return response()->json($diagnoses);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Diagnosis::class);

        $validated = $request->validate([
            'consultation_id' => [
                'required',
                'integer',
                'exists:consultations,id',
            ],

            'diagnosis_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'severity' => [
                'nullable',
                'in:mild,moderate,severe,critical',
            ],

            'is_confirmed' => [
                'sometimes',
                'boolean',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);


        $consultation = Consultation::with('appointment')
            ->findOrFail($validated['consultation_id']);

        // Only the doctor assigned to this consultation can add a diagnosis .
        if ((int) $consultation->doctor_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to diagnose this consultation.',
            ], 403);
        }


        if (in_array($consultation->appointment->status, ['cancelled', 'no_show'])) {
            return response()->json([
                'message' => 'Cannot add a diagnosis to a cancelled or no-show appointment.',
            ], 422);
        }

        $diagnosis = Diagnosis::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $consultation->patient_id,
            'doctor_id' => $request->user()->id,
            'diagnosis_name' => $validated['diagnosis_name'],
            'description' => $validated['description'] ?? null,
            'severity' => $validated['severity'] ?? null,
            // A diagnosis must be explicitly confirmed by the doctor.
            'is_confirmed' => $validated['is_confirmed'] ?? false,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Diagnosis created successfully.',
            'data' => $diagnosis->load([
                'consultation',
                'patient',
                'doctor',
            ]),
        ], 201);
    }

    public function show(Diagnosis $diagnosis): JsonResponse
    {
        $this->authorize('view', $diagnosis);

        return response()->json([
            'data' => $diagnosis->load([
                'consultation',
                'patient',
                'doctor',
            ]),
        ]);
    }

    public function update(Request $request, Diagnosis $diagnosis): JsonResponse
    {
        $this->authorize('update', $diagnosis);

        // Only the doctor assigned to this consultation can update it.
        if ((int) $diagnosis->doctor_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to update this diagnosis.',
            ], 403);
        }

        $validated = $request->validate([
            'diagnosis_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'severity' => [
                'sometimes',
                'nullable',
                'in:mild,moderate,severe,critical',
            ],

            'is_confirmed' => [
                'sometimes',
                'boolean',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $diagnosis->update($validated);

        return response()->json([
            'message' => 'Diagnosis updated successfully.',
            'data' => $diagnosis->fresh()->load([
                'consultation',
                'patient',
                'doctor',
            ]),
        ]);
    }
}
