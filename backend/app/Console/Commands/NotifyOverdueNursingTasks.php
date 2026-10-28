<?php

namespace App\Console\Commands;

use App\Models\NursingTask;
use App\Notifications\NursingTaskOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyOverdueNursingTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nursing:notify-overdue {--grace=5 : Minutes past due before notifying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify assigned nurses when nursing tasks become overdue.';

    public function handle(): int
    {
        $graceMinutes = (int) $this->option('grace');
        $now = Carbon::now();

        $tasks = NursingTask::query()
            ->with(['patient', 'assignedNurse'])
            ->whereNull('completed_at')
            ->where('status', '!=', NursingTask::STATUS_CANCELLED)
            ->whereNull('overdue_notified_at')
            ->whereNotNull('assigned_nurse_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now->copy()->subMinutes($graceMinutes))
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $nurse = $task->assignedNurse;
            $patient = $task->patient;

            if (! $nurse || ! $patient) {
                continue;
            }

            $minutesOverdue = (int) $task->due_at->diffInMinutes($now);

            $nurse->notify(new NursingTaskOverdueNotification(
                taskId: $task->id,
                patientId: $task->patient_id,
                patientName: trim("{$patient->first_name} {$patient->last_name}"),
                title: $task->title,
                taskType: $task->task_type,
                priority: $task->priority,
                scheduledAt: $task->scheduled_at?->toIso8601String() ?? '',
                dueAt: $task->due_at->toIso8601String(),
                minutesOverdue: $minutesOverdue,
            ));

            $task->update(['overdue_notified_at' => $now]);

            $count++;
        }

        $this->info("Sent {$count} overdue nursing task notification(s).");

        return self::SUCCESS;
    }
}
