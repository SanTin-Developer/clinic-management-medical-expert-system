<?php

namespace Database\Factories;

use App\Models\PublicPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicPage>
 */
class PublicPageFactory extends Factory
{
    protected $model = PublicPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'status' => PublicPage::STATUS_DRAFT,
            'is_system' => false,
            'show_in_footer' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublicPage::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
