<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 5, 200),
            'method' => fake()->randomElement(['cash', 'card', 'bank_transfer']),
            'reference' => null,
            'paid_at' => now(),
            'received_by' => User::factory(),
            'notes' => null,
        ];
    }
}
