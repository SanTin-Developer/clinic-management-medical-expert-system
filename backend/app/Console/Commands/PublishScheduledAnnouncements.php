<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Console\Command;

/**
 * Publishes announcements whose scheduled publication time has arrived.
 * Idempotent: only announcements that are still unpublished and whose
 * published_at is due (and are not archived) are published, and each one is
 * notified at most once.
 */
class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled';

    protected $description = 'Publish announcements whose scheduled time has arrived.';

    public function handle(AnnouncementService $announcements): int
    {
        $due = Announcement::query()
            ->where('is_published', false)
            ->whereNull('archived_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $announcement) {
            $announcements->publish($announcement);
        }

        $this->info("Published {$due->count()} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
