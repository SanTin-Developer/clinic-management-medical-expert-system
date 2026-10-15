<?php

namespace App\Services\Nursing;

use App\Models\NursingTask;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Support\Arr;

/**
 * Persists vital sign measurements. Values are only ever taken from the data
 * supplied by the recording nurse; nothing is fabricated or defaulted.
 */
class VitalSignService
{
    /**
     * The clinical measurement fields a vital signs record may contain.
     *
     * @var list<string>
     */
    public const MEASUREMENT_FIELDS = [
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'respiratory_rate',
        'oxygen_saturation',
        'weight',
        'height',
        'symptoms',
        'notes',
    ];

    /**
     * Record vitals captured while completing a nursing task. The record is
     * always linked to the task, its patient, the task's admission (or an
     * explicitly supplied admission) and the authenticated nurse.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordForTask(NursingTask $task, User $actor, array $data): VitalSign
    {
        $attributes = Arr::only($data, self::MEASUREMENT_FIELDS);

        $attributes['patient_id'] = $task->patient_id;
        $attributes['admission_id'] = $data['admission_id'] ?? $task->admission_id;
        $attributes['nursing_task_id'] = $task->id;
        $attributes['nurse_id'] = $actor->id;

        return VitalSign::create($attributes);
    }
}
