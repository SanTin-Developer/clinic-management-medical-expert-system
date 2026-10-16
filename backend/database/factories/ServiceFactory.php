<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => ucfirst($title),
            'slug' => Str::slug($title),
            'icon' => fake()->randomElement(['Stethoscope', 'HeartPulse', 'Baby', 'Syringe', 'Activity']),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'is_featured' => false,
            'is_published' => false,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
        ]);
    }
}
