<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAssignment>
 */
class PatientAssignmentFactory extends Factory
{
    protected $model = PatientAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nurse_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'status' => PatientAssignment::STATUS_ACTIVE,
            'shift' => fake()->randomElement(['morning', 'evening', 'night']),
            'starts_at' => now()->subHour(),
        ];
    }
}
