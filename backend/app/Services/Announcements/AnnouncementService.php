<?php

namespace App\Services\Announcements;

use App\Jobs\SendTelegramMessage;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMessages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Centralized audience targeting, publishing, notifications and read tracking
 * for announcements. Audience membership is always derived from the
 * authenticated user's real roles — never from client-supplied values.
 */
class AnnouncementService
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly AnnouncementPosterStorage $posters,
    ) {}

    /**
     * Roles considered "staff" (i.e. non-patient clinic users).
     *
     * @var list<string>
     */
    public const STAFF_ROLES = [
        'Admin',
        'Doctor',
        'Nurse',
        'Head Nurse',
        'Receptionist',
        'Pharmacist',
    ];

    /**
     * The roles that belong to each targeted audience.
     *
     * @var array<string, list<string>>
     */
    public const ROLES_BY_AUDIENCE = [
        'staff' => self::STAFF_ROLES,
        'doctors' => ['Doctor'],
        'nurses' => ['Nurse', 'Head Nurse'],
        'receptionists' => ['Receptionist'],
        'pharmacists' => ['Pharmacist'],
        'patients' => ['Patient'],
    ];

    /**
     * The audience buckets a given user belongs to. "all" always applies.
     *
     * @return list<string>
     */
    public function audiencesFor(User $user): array
    {
        $roles = $user->getRoleNames();
        $audiences = ['all'];

        if ($roles->intersect(self::STAFF_ROLES)->isNotEmpty()) {
            $audiences[] = 'staff';
        }

        if ($roles->contains('Doctor')) {
            $audiences[] = 'doctors';
        }

        if ($roles->contains('Nurse') || $roles->contains('Head Nurse')) {
            $audiences[] = 'nurses';
        }

        if ($roles->contains('Receptionist')) {
            $audiences[] = 'receptionists';
        }

        if ($roles->contains('Pharmacist')) {
            $audiences[] = 'pharmacists';
        }

        if ($roles->contains('Patient')) {
            $audiences[] = 'patients';
        }

        return array_values(array_unique($audiences));
    }

    /**
     * Published, non-archived, non-expired announcements the user is entitled
     * to read, based on their real roles.
     *
     * @return Builder<Announcement>
     */
    public function visibleTo(User $user): Builder
    {
        return Announcement::query()
            ->with('author:id,name')
            ->published()
            ->whereIn('audience', $this->audiencesFor($user));
    }

    public function canView(User $user, Announcement $announcement): bool
    {
        return $announcement->isLive()
            && in_array($announcement->audience, $this->audiencesFor($user), true);
    }

    /**
     * Users who should receive an in-app notification for the announcement,
     * honouring the per-user "system announcements" preference and excluding
     * the author.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(Announcement $announcement): Collection
    {
        $query = User::query();

        if ($announcement->audience !== 'all') {
            $query->role(self::ROLES_BY_AUDIENCE[$announcement->audience] ?? []);
        }

        if ($announcement->created_by !== null) {
            $query->whereKeyNot($announcement->created_by);
        }

        $recipients = $query->get();

        $disabled = NotificationPreference::query()
            ->where('system_announcements', false)
            ->pluck('user_id');

        if ($disabled->isNotEmpty()) {
            $recipients = $recipients->reject(fn (User $user): bool => $disabled->contains($user->id));
        }

        return $recipients->values();
    }

    /**
     * Publish an announcement. Idempotent: notifications are sent only on the
     * transition from unpublished to published.
     */
    public function publish(Announcement $announcement): Announcement
    {
        $wasPublished = $announcement->is_published === true;

        if ($announcement->published_at === null || $announcement->published_at->isFuture()) {
            $announcement->published_at = now();
        }

        $announcement->is_published = true;
        $announcement->archived_at = null;
        $announcement->save();

        if (! $wasPublished) {
            $this->notify($announcement);
            $this->notifyTelegram($announcement);
        }

        return $announcement;
    }

    public function notify(Announcement $announcement): void
    {
        $recipients = $this->recipientsFor($announcement);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new SystemAnnouncementNotification(
                $announcement->title,
                $announcement->body,
                '/news',
                $announcement->id,
                $announcement->priority,
            ),
        );
    }

    /**
     * Broadcast a published announcement to every user in its audience who has
     * a verified Telegram connection and has not opted out of Telegram
     * notifications. The author is excluded.
     *
     * @return Collection<int, User>
     */
    public function telegramRecipientsFor(Announcement $announcement): Collection
    {
        $query = User::query()
            ->with('telegramConnection')
            ->whereHas('telegramConnection', function (Builder $builder): void {
                $builder->whereNotNull('verified_at')->whereNotNull('telegram_chat_id');
            });

        if ($announcement->audience !== 'all') {
            $query->role(self::ROLES_BY_AUDIENCE[$announcement->audience] ?? []);
        }

        if ($announcement->created_by !== null) {
            $query->whereKeyNot($announcement->created_by);
        }

        $recipients = $query->get();

        $disabled = NotificationPreference::query()
            ->where('telegram', false)
            ->pluck('user_id');

        if ($disabled->isNotEmpty()) {
            $recipients = $recipients->reject(fn (User $user): bool => $disabled->contains($user->id));
        }

        return $recipients->values();
    }

    public function notifyTelegram(Announcement $announcement): void
    {
        if (! $this->telegram->isConfigured()) {
            return;
        }

        $text = TelegramMessages::announcement(
            $announcement->title,
            $announcement->body,
            $announcement->priority,
        );

        $poster = $announcement->poster_path;
        $external = $this->posters->isExternal($poster);

        foreach ($this->telegramRecipientsFor($announcement) as $user) {
            $chatId = $user->telegramConnection?->telegram_chat_id;

            if ($chatId === null) {
                continue;
            }

            SendTelegramMessage::dispatch(
                chatId: $chatId,
                text: $text,
                userId: $user->id,
                eventType: 'announcement',
                subject: $announcement->title,
                idempotencyKey: 'announcement:'.$announcement->id.':user:'.$user->id,
                photoDisk: $external ? null : $this->posters->disk(),
                photoPath: $external ? null : $poster,
                photoUrl: $external ? $poster : null,
            );
        }
    }

    public function markRead(User $user, Announcement $announcement): void
    {
        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /**
     * Mark every currently-visible announcement as read for the user.
     * Idempotent: existing receipts are left untouched.
     */
    public function markAllRead(User $user): int
    {
        $ids = $this->visibleTo($user)->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $rows = $ids->map(fn (int $id): array => [
            'announcement_id' => $id,
            'user_id' => $user->id,
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        AnnouncementRead::query()->insertOrIgnore($rows);

        return count($rows);
    }

    public function unreadCount(User $user): int
    {
        return $this->visibleTo($user)
            ->whereDoesntHave('reads', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $now = now();

        return [
            'total' => Announcement::query()->count(),
            'published' => Announcement::query()
                ->where('is_published', true)
                ->notArchived()
                ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->count(),
            'draft' => Announcement::query()
                ->where('is_published', false)
                ->notArchived()
                ->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->count(),
            'scheduled' => Announcement::query()
                ->where('is_published', false)
                ->notArchived()
                ->whereNotNull('published_at')
                ->where('published_at', '>', $now)
                ->count(),
            'urgent' => Announcement::query()->where('priority', 'urgent')->count(),
            'archived' => Announcement::query()->whereNotNull('archived_at')->count(),
        ];
    }

    /**
     * Readership snapshot for a single announcement.
     *
     * @return array<string, mixed>
     */
    public function readership(Announcement $announcement): array
    {
        $recipients = $this->recipientsFor($announcement);
        $recipientIds = $recipients->pluck('id');

        $reads = AnnouncementRead::query()
            ->where('announcement_id', $announcement->id)
            ->whereIn('user_id', $recipientIds)
            ->with('user:id,name')
            ->latest('read_at')
            ->get();

        $readIds = $reads->pluck('user_id');

        return [
            'recipients' => $recipientIds->count(),
            'read' => $reads->count(),
            'unread' => max($recipientIds->count() - $reads->count(), 0),
            'read_recipients' => $reads->map(fn (AnnouncementRead $read): array => [
                'id' => $read->user_id,
                'name' => $read->user?->name,
                'read_at' => $read->read_at?->toIso8601String(),
            ])->values(),
            'unread_recipients' => $recipients
                ->reject(fn (User $user): bool => $readIds->contains($user->id))
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->values(),
        ];
    }
}
