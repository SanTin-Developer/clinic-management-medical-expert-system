<?php

namespace Database\Factories;

use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    protected $model = ShiftTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Morning '.fake()->unique()->numberBetween(1, 99999),
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
            'crosses_midnight' => false,
            'clock_out_opens_at' => '14:30',
            'clock_out_closes_at' => '15:00',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function night(): static
    {
        return $this->state(fn (): array => [
            'type' => 'night',
            'start_time' => '21:30',
            'clock_in_cutoff' => '22:00',
            'end_time' => '07:00',
            'crosses_midnight' => true,
            'clock_out_opens_at' => '07:00',
            'clock_out_closes_at' => '07:30',
        ]);
    }
}
