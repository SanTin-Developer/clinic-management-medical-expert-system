<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        return [
            'prescription_number' => 'RX-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'consultation_id' => null,
            'status' => 'pending',
            'notes' => null,
            'issued_at' => now(),
            'dispensed_at' => null,
            'dispensed_by' => null,
        ];
    }

    public function dispensed(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dispensed',
            'dispensed_at' => now(),
            'dispensed_by' => $by?->id ?? User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
