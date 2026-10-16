<?php

namespace Database\Factories;

use App\Models\PublicTeamProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicTeamProfile>
 */
class PublicTeamProfileFactory extends Factory
{
    protected $model = PublicTeamProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.fake()->name(),
            'position' => fake()->randomElement(['General Practitioner', 'Cardiologist', 'Pediatrician', 'Surgeon']),
            'specialization' => fake()->words(2, true),
            'bio' => fake()->paragraph(),
            'photo_media_id' => null,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_featured' => false,
            'is_published' => false,
            'sort_order' => fake()->numberBetween(0, 20),
            'staff_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
        ]);
    }
}
