<?php

namespace Database\Factories;

use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    protected $model = Ward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ward '.fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->bothify('W##')),
            'floor' => (string) fake()->numberBetween(1, 5),
            'ward_type' => Ward::TYPE_GENERAL,
            'capacity' => fake()->numberBetween(4, 30),
            'is_active' => true,
        ];
    }
}
