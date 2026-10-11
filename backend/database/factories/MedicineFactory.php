<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'MED-'.strtoupper(fake()->unique()->numerify('######')),
            'name' => fake()->unique()->words(2, true),
            'generic_name' => fake()->word(),
            'category' => fake()->randomElement(['analgesic', 'antibiotic', 'antihistamine']),
            'form' => fake()->randomElement(['tablet', 'capsule', 'syrup']),
            'strength' => fake()->randomElement(['250mg', '500mg', '100mg']),
            'unit' => 'tablet',
            'manufacturer' => fake()->company(),
            'price' => fake()->randomFloat(2, 1, 100),
            'reorder_level' => 10,
            'is_active' => true,
        ];
    }
}
