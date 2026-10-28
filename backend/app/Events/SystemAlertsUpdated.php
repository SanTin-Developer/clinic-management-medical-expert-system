<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight signal telling clients that their alert queries are stale.
 *
 * It intentionally carries no alert id, category, patient, amount, or URL:
 * every client still fetches through the permission-filtered API.
 */
class SystemAlertsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('system-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'system.alerts.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
