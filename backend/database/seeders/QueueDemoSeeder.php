<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class QueueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today()->toDateString();
        if (QueueEntry::where('queue_date', $today)->count() >= 5) {
            return;
        }

        $doctor = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'doctor');
        })->first() ?? User::first();

        $patients = Patient::query()->take(5)->get();
        if ($patients->isEmpty()) {
            return;
        }

        $statuses = ['waiting', 'in_consultation', 'called', 'completed'];

        for ($i = 0; $i < 5; $i++) {
            $patient = $patients[$i % $patients->count()];
            $appointment = Appointment::query()->where('patient_id', $patient->id)->first();
            $ticket = str_pad((string) ($i + 10), 3, '0', STR_PAD_LEFT);

            QueueEntry::updateOrCreate(
                [
                    'queue_date' => $today,
                    'ticket_number' => $ticket,
                ],
                [
                    'appointment_id' => $appointment?->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor?->id,
                    'status' => $statuses[$i % count($statuses)],
                    'priority' => $i === 0 ? 'urgent' : 'normal',
                    'called_at' => $i >= 2 ? Carbon::now()->subMinutes(rand(1, 10)) : null,
                    'started_at' => $i >= 2 ? Carbon::now()->subMinutes(rand(1, 5)) : null,
                    'completed_at' => $i >= 3 ? Carbon::now()->subMinutes(rand(0, 3)) : null,
                    'notes' => 'Demo queue entry',
                ]
            );
        }
    }
}
