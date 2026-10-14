<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_status' => User::STATUS_ACTIVE,
            'activated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * An invited staff account that has not yet been activated.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => User::STATUS_PENDING,
            'activated_at' => null,
        ]);
    }

    /**
     * A staff account that accepted its invitation and is awaiting admin review.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => User::STATUS_PENDING_APPROVAL,
            'activated_at' => null,
        ]);
    }

    /**
     * A staff account whose registration was rejected by an administrator.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => User::STATUS_REJECTED,
            'activated_at' => null,
            'rejected_at' => now(),
            'rejection_reason' => 'Incomplete documentation',
        ]);
    }

    /**
     * A suspended account.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => User::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);
    }

    /**
     * An account with a confirmed TOTP secret (base32, plaintext in the factory).
     */
    public function withTwoFactor(string $secret): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => null,
        ]);
    }
}
