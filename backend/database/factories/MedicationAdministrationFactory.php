<?php

namespace Database\Factories;

use App\Models\MedicationAdministration;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicationAdministration>
 */
class MedicationAdministrationFactory extends Factory
{
    protected $model = MedicationAdministration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'dose' => '1 tablet',
            'route' => 'oral',
            'scheduled_at' => now()->addHour(),
            'status' => MedicationAdministration::STATUS_SCHEDULED,
        ];
    }
}
