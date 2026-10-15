<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'medicine_id' => Medicine::factory(),
            'batch_number' => 'BATCH-'.strtoupper(fake()->unique()->numerify('######')),
            'quantity' => fake()->numberBetween(10, 200),
            'expiration_date' => fake()->dateTimeBetween('+2 months', '+2 years')->format('Y-m-d'),
            'received_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'location' => 'Pharmacy Shelf '.fake()->randomLetter(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration_date' => fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
        ]);
    }
}
