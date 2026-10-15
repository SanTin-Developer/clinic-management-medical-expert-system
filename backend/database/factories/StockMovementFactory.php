<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'medicine_id' => Medicine::factory(),
            'inventory_item_id' => null,
            'user_id' => User::factory(),
            'type' => 'in',
            'quantity' => fake()->numberBetween(1, 50),
            'reason' => fake()->sentence(3),
            'reference' => null,
        ];
    }

    public function forItem(InventoryItem $item): static
    {
        return $this->state(fn (array $attributes) => [
            'medicine_id' => $item->medicine_id,
            'inventory_item_id' => $item->id,
        ]);
    }
}
