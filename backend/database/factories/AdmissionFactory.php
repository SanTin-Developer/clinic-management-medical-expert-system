<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_number' => 'ADM-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'status' => Admission::STATUS_ADMITTED,
            'admitted_at' => now(),
            'reason' => fake()->sentence(),
        ];
    }
}
