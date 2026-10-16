<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraphs(4, true),
            'cover_media_id' => null,
            'category' => fake()->randomElement(['Health Tips', 'Clinic News', 'Prevention', 'Nutrition']),
            'tags' => fake()->words(3),
            'status' => Article::STATUS_DRAFT,
            'is_featured' => false,
            'views' => 0,
            'published_at' => null,
            'author_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
