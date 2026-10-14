<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'room_number' => fake()->unique()->bothify('R-###'),
            'room_type' => Room::TYPE_GENERAL,
            'capacity' => fake()->numberBetween(1, 6),
            'price_per_day' => fake()->randomElement([15, 30, 55, 80]),
            'is_active' => true,
        ];
    }
}
