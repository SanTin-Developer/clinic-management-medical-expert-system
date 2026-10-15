<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_template_id' => ShiftTemplate::factory(),
            'session_date' => now()->toDateString(),
            'action' => AttendanceSession::ACTION_CLOCK_IN,
            'status' => AttendanceSession::STATUS_OPEN,
            'opened_by' => null,
            'opened_at' => now(),
            'closes_at' => now()->addHours(2),
            'closed_by' => null,
            'closed_at' => null,
            'close_reason' => null,
        ];
    }
}
