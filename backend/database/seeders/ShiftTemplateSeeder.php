<?php

namespace Database\Seeders;

use App\Models\ShiftTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the clinic's three default rotating shifts. Idempotent via the unique
 * template name.
 */
class ShiftTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Morning Shift',
                'type' => 'day',
                'start_time' => '07:30',
                'clock_in_cutoff' => '08:00',
                'end_time' => '14:30',
                'crosses_midnight' => false,
                'clock_out_opens_at' => '14:30',
                'clock_out_closes_at' => '15:00',
                'sort_order' => 1,
            ],
            [
                'name' => 'Evening Shift',
                'type' => 'evening',
                'start_time' => '14:30',
                'clock_in_cutoff' => '15:00',
                'end_time' => '21:30',
                'crosses_midnight' => false,
                'clock_out_opens_at' => '21:30',
                'clock_out_closes_at' => '22:00',
                'sort_order' => 2,
            ],
            [
                'name' => 'Night Watch',
                'type' => 'night',
                'start_time' => '21:30',
                'clock_in_cutoff' => '22:00',
                'end_time' => '07:00',
                'crosses_midnight' => true,
                'clock_out_opens_at' => '07:00',
                'clock_out_closes_at' => '07:30',
                'sort_order' => 3,
            ],
        ];

        foreach ($templates as $template) {
            ShiftTemplate::updateOrCreate(
                ['name' => $template['name']],
                array_merge($template, ['is_active' => true]),
            );
        }
    }
}
