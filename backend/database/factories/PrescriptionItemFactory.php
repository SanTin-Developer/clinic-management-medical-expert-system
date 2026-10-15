<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    protected $model = PrescriptionItem::class;

    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medicine_id' => Medicine::factory(),
            'dosage' => fake()->randomElement(['1 tablet', '2 tablets', '5ml']),
            'frequency' => fake()->randomElement(['once daily', 'twice daily', 'three times daily']),
            'duration' => fake()->randomElement(['3 days', '5 days', '7 days']),
            'quantity' => fake()->numberBetween(1, 30),
            'instructions' => fake()->optional()->sentence(),
        ];
    }
}
