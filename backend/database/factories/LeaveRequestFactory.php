<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(0, 4).' days');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(LeaveRequest::TYPES),
            'start_date' => $start,
            'end_date' => $end,
            'days' => 1,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }
}
