<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PatientAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $assignmentId,
        public int $patientId,
        public string $patientName,
        public ?string $wardName = null,
        public ?string $shift = null,
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
            'kind' => 'patient_assigned',
            'assignment_id' => $this->assignmentId,
            'patient_id' => $this->patientId,
            'patient_name' => $this->patientName,
            'ward_name' => $this->wardName,
            'shift' => $this->shift,
            'url' => '/patients/'.$this->patientId,
        ];
    }
}
