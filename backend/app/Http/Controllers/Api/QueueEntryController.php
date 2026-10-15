<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\QueueEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', QueueEntry::class);

        $date = $request->query('date', now()->toDateString());

        $query = QueueEntry::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'doctor:id,name', 'appointment:id,appointment_number'])
            ->whereDate('queue_date', $date);

        $base = clone $query;

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('ticket_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($patientQuery) use ($search) {
                        $patientQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('patient_number', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query->ordered()->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'waiting' => (clone $base)->where('status', 'waiting')->count(),
            'called' => (clone $base)->where('status', 'called')->count(),
            'serving' => (clone $base)->where('status', 'serving')->count(),
            'done' => (clone $base)->where('status', 'done')->count(),
        ];

        return response()->json(array_merge($entries->toArray(), ['stats' => $stats, 'date' => $date]));
    }

    public function appointments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', QueueEntry::class);

        $date = $request->query('date', now()->toDateString());

        $appointments = Appointment::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'doctor:id,name'])
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('appointment_time')
            ->get();

        $queued = QueueEntry::query()
            ->whereDate('queue_date', $date)
            ->whereNotNull('appointment_id')
            ->pluck('appointment_id')
            ->all();

        $data = $appointments->map(fn (Appointment $appointment): array => array_merge(
            $appointment->toArray(),
            ['already_queued' => in_array($appointment->id, $queued, true)],
        ));

        return response()->json(['data' => $data, 'date' => $date]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', QueueEntry::class);

        $validated = $request->validate([
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(QueueEntry::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'queue_date' => ['sometimes', 'date'],
        ]);

        if (empty($validated['appointment_id']) && empty($validated['patient_id'])) {
            throw ValidationException::withMessages([
                'patient_id' => 'Select a patient or link an appointment.',
            ]);
        }

        if (! empty($validated['appointment_id'])) {
            $appointment = Appointment::findOrFail($validated['appointment_id']);

            $alreadyQueued = QueueEntry::query()
                ->where('appointment_id', $appointment->id)
                ->whereDate('queue_date', $validated['queue_date'] ?? now()->toDateString())
                ->exists();

            if ($alreadyQueued) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'That appointment is already in the queue.',
                ]);
            }

            $validated['patient_id'] = $appointment->patient_id;
            $validated['doctor_id'] = $validated['doctor_id'] ?? $appointment->doctor_id;
        }

        $date = $validated['queue_date'] ?? now()->toDateString();

        $entry = QueueEntry::create([
            'patient_id' => $validated['patient_id'],
            'appointment_id' => $validated['appointment_id'] ?? null,
            'doctor_id' => $validated['doctor_id'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'notes' => $validated['notes'] ?? null,
            'queue_date' => $date,
            'ticket_number' => $this->nextTicketNumber($date),
            'status' => 'waiting',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Added to the queue.',
            'data' => $entry->fresh(['patient:id,first_name,last_name,patient_number', 'doctor:id,name']),
        ], 201);
    }

    public function show(QueueEntry $queueEntry): JsonResponse
    {
        $this->authorize('view', $queueEntry);

        return response()->json([
            'data' => $queueEntry->load(['patient', 'doctor:id,name', 'appointment:id,appointment_number']),
        ]);
    }

    public function update(Request $request, QueueEntry $queueEntry): JsonResponse
    {
        $this->authorize('update', $queueEntry);

        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(QueueEntry::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $queueEntry->update($validated);

        return response()->json([
            'message' => 'Queue entry updated.',
            'data' => $queueEntry->fresh(['patient:id,first_name,last_name,patient_number', 'doctor:id,name']),
        ]);
    }

    public function destroy(QueueEntry $queueEntry): JsonResponse
    {
        $this->authorize('delete', $queueEntry);

        $queueEntry->delete();

        return response()->json(['message' => 'Queue entry removed.']);
    }

    public function call(QueueEntry $queueEntry): JsonResponse
    {
        return $this->transition($queueEntry, 'called', ['called_at' => now()]);
    }

    public function start(QueueEntry $queueEntry): JsonResponse
    {
        return $this->transition($queueEntry, 'serving', ['started_at' => now()]);
    }

    public function complete(QueueEntry $queueEntry): JsonResponse
    {
        return $this->transition($queueEntry, 'done', ['completed_at' => now()]);
    }

    public function skip(QueueEntry $queueEntry): JsonResponse
    {
        return $this->transition($queueEntry, 'skipped', []);
    }

    public function cancel(QueueEntry $queueEntry): JsonResponse
    {
        return $this->transition($queueEntry, 'cancelled', []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(QueueEntry $queueEntry, string $status, array $attributes): JsonResponse
    {
        $this->authorize('update', $queueEntry);

        $queueEntry->update(array_merge(['status' => $status], $attributes));

        return response()->json([
            'message' => "Queue entry marked as {$status}.",
            'data' => $queueEntry->fresh(['patient:id,first_name,last_name,patient_number', 'doctor:id,name']),
        ]);
    }

    private function nextTicketNumber(string $date): string
    {
        $count = QueueEntry::query()->whereDate('queue_date', $date)->count();

        do {
            $count++;
            $ticket = 'Q-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        } while (QueueEntry::query()->whereDate('queue_date', $date)->where('ticket_number', $ticket)->exists());

        return $ticket;
    }
}
