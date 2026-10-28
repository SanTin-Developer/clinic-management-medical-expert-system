<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the Queue Management page.
 *
 * Creates today's outpatient queue with a realistic mix of waiting, called,
 * serving, done, skipped and cancelled entries, plus a few free appointments
 * so the "From appointments" picker is not empty.
 *
 * Idempotent: if the queue for today already has entries it does nothing, so
 * staff changes made while demoing are never overwritten.
 */
class QueueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        if (QueueEntry::query()->whereDate('queue_date', $today)->exists()) {
            return;
        }

        /** @var Collection<int, Patient> $patients */
        $patients = Patient::query()->orderBy('id')->take(14)->get();

        if ($patients->isEmpty()) {
            return;
        }

        /** @var Collection<int, User> $doctors */
        $doctors = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Doctor'))
            ->orderBy('id')
            ->get();

        $receptionist = User::query()->where('username', 'receptionist')->first();
        $createdBy = $receptionist?->id;

        // [status, priority, has appointment, doctor index (null = unassigned), notes]
        $rows = [
            ['waiting', 'urgent', true, 0, 'Chest tightness - triage before the routine queue.'],
            ['waiting', 'normal', false, null, 'Walk-in without an appointment.'],
            ['serving', 'urgent', true, 1, 'Severe headache with high blood pressure.'],
            ['called', 'normal', true, 0, null],
            ['done', 'normal', true, 1, 'Reviewed lab results and adjusted medication.'],
            ['done', 'normal', true, 0, null],
            ['waiting', 'urgent', true, 1, 'Child with fever - keep at the front of the queue.'],
            ['called', 'normal', true, 0, null],
            ['done', 'normal', true, 1, null],
            ['skipped', 'normal', true, 0, 'Patient stepped out; asked to be called again.'],
            ['waiting', 'normal', true, 1, null],
            ['cancelled', 'normal', false, null, 'Patient left before being seen.'],
            ['serving', 'normal', true, 0, null],
            ['waiting', 'normal', true, 1, 'Follow-up after dengue treatment.'],
        ];

        $appointmentReasons = [
            'General consultation',
            'Fever and body aches',
            'Blood pressure follow-up',
            'Persistent cough',
            'Stomach pain',
            'Routine health check',
            'Wound dressing review',
            'Medication review',
            'Child vaccination consult',
            'Headache and dizziness',
            'Diabetes follow-up',
            'Skin rash',
            'Back pain assessment',
            'Post-treatment review',
        ];

        DB::transaction(function () use ($rows, $patients, $doctors, $today, $createdBy, $appointmentReasons): void {
            $appointmentSequence = 0;

            foreach ($rows as $index => $row) {
                [$status, $priority, $hasAppointment, $doctorIndex, $notes] = $row;

                $patient = $patients[$index % $patients->count()];
                $doctor = $doctorIndex === null || $doctors->isEmpty()
                    ? null
                    : $doctors[$doctorIndex % $doctors->count()];

                // Spread arrivals across the last ~100 minutes so "Added" looks live.
                $createdAt = now()->subMinutes(100 - ($index * 7));

                $appointment = null;

                if ($hasAppointment) {
                    $appointmentSequence++;

                    $appointment = Appointment::query()->updateOrCreate(
                        ['appointment_number' => sprintf('APT-%s-Q%03d', $today->format('Ymd'), $appointmentSequence)],
                        [
                            'patient_id' => $patient->id,
                            'doctor_id' => $doctor?->id,
                            'appointment_date' => $today->toDateString(),
                            'appointment_time' => $createdAt->copy()->subMinutes(15)->format('H:i'),
                            'reason' => $appointmentReasons[$index % count($appointmentReasons)],
                            'status' => $status === 'done' ? 'completed' : 'confirmed',
                            'note' => 'Queue demo appointment.',
                        ]
                    );
                }

                $timeline = match ($status) {
                    'called' => [
                        'called_at' => now()->subMinutes(4),
                    ],
                    'serving' => [
                        'called_at' => now()->subMinutes(14),
                        'started_at' => now()->subMinutes(11),
                    ],
                    'done' => [
                        'called_at' => $createdAt->copy()->addMinutes(8),
                        'started_at' => $createdAt->copy()->addMinutes(11),
                        'completed_at' => $createdAt->copy()->addMinutes(28),
                    ],
                    default => [],
                };

                $entry = QueueEntry::query()->create(array_merge([
                    'patient_id' => $patient->id,
                    'appointment_id' => $appointment?->id,
                    'doctor_id' => $doctor?->id,
                    'ticket_number' => sprintf('Q-%03d', $index + 1),
                    'status' => $status,
                    'priority' => $priority,
                    'queue_date' => $today->toDateString(),
                    'notes' => $notes,
                    'created_by' => $createdBy,
                ], $timeline));

                $entry->forceFill(['created_at' => $createdAt])->save();
            }

            // A few today's appointments that are not in the queue yet, so the
            // "From appointments" picker has selectable rows.
            $freeAppointments = [
                ['patient_index' => 2, 'time' => '15:00', 'reason' => 'Afternoon general consultation'],
                ['patient_index' => 5, 'time' => '15:30', 'reason' => 'Wound care follow-up'],
                ['patient_index' => 8, 'time' => '16:00', 'reason' => 'Vaccination appointment'],
            ];

            foreach ($freeAppointments as $offset => $free) {
                $patient = $patients[$free['patient_index'] % $patients->count()];

                Appointment::query()->updateOrCreate(
                    ['appointment_number' => sprintf('APT-%s-A%03d', $today->format('Ymd'), $offset + 1)],
                    [
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctors->isEmpty() ? null : $doctors[$offset % $doctors->count()]->id,
                        'appointment_date' => $today->toDateString(),
                        'appointment_time' => $free['time'],
                        'reason' => $free['reason'],
                        'status' => 'scheduled',
                        'note' => 'Queue demo appointment (not queued yet).',
                    ]
                );
            }
        });
    }
}
