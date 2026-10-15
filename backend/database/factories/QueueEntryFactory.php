<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    protected $model = QueueEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'doctor_id' => null,
            'ticket_number' => 'Q-'.fake()->unique()->numerify('####'),
            'status' => 'waiting',
            'priority' => fake()->randomElement(QueueEntry::PRIORITIES),
            'queue_date' => now()->toDateString(),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
