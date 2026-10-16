<?php

namespace Database\Factories;

use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'caption' => fake()->sentence(),
            'image_media_id' => null,
            'category' => fake()->randomElement(['Facilities', 'Equipment', 'Staff', 'Events']),
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
