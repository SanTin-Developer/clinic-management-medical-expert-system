<?php

namespace Database\Factories;

use App\Models\NursingTask;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NursingTask>
 */
class NursingTaskFactory extends Factory
{
    protected $model = NursingTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'task_type' => NursingTask::TYPE_OBSERVATION,
            'title' => fake()->sentence(3),
            'priority' => 'normal',
            'scheduled_at' => now()->addHour(),
            'status' => NursingTask::STATUS_PENDING,
        ];
    }
}
