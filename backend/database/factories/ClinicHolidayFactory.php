<?php

namespace Database\Factories;

use App\Models\ClinicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicHoliday>
 */
class ClinicHolidayFactory extends Factory
{
    protected $model = ClinicHoliday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holiday_date' => fake()->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'name' => fake()->words(2, true),
            'is_recurring' => false,
            'notes' => null,
        ];
    }
}
