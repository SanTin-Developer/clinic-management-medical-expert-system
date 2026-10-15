<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'consultation_id' => null,
            'status' => 'unpaid',
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'amount_paid' => 0,
            'balance' => $subtotal,
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
