<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NursingTaskOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public int $patientId,
        public string $patientName,
        public string $title,
        public string $taskType,
        public string $priority,
        public string $scheduledAt,
        public string $dueAt,
        public int $minutesOverdue,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'nursing_task_overdue',
            'task_id' => $this->taskId,
            'patient_id' => $this->patientId,
            'patient_name' => $this->patientName,
            'title' => $this->title,
            'task_type' => $this->taskType,
            'priority' => $this->priority,
            'scheduled_at' => $this->scheduledAt,
            'due_at' => $this->dueAt,
            'minutes_overdue' => $this->minutesOverdue,
            'url' => '/patients/'.$this->patientId,
        ];
    }
}
