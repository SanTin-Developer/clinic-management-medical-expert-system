<?php

namespace Database\Factories;

use App\Models\WebsiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteSetting>
 */
class WebsiteSettingFactory extends Factory
{
    protected $model = WebsiteSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_name' => 'RoyalCare',
            'tagline' => fake()->catchPhrase(),
            'hero_title' => fake()->sentence(5),
            'hero_subtitle' => fake()->sentence(12),
            'about_summary' => fake()->paragraph(),
            'primary_phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
        ];
    }
}
