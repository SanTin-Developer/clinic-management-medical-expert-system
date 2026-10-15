<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_date' => fake()->dateTimeBetween('-1 week', '+1 month')->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'type' => 'day',
            'status' => 'scheduled',
            'notes' => null,
            'created_by' => null,
        ];
    }
}
