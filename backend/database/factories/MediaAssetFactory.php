<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2).'.jpg';

        return [
            'name' => $name,
            'file_path' => 'website/media/'.$name,
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1_000, 500_000),
            'width' => 1200,
            'height' => 800,
            'uploaded_by' => null,
        ];
    }
}
