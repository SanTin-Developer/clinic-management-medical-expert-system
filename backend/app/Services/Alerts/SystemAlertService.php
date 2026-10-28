<?php

namespace App\Services\Alerts;

use App\Events\SystemAlertsUpdated;
use App\Jobs\SendTelegramMessage;
use App\Models\SystemAlert;
use App\Models\TelegramGroupConfig;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;
use App\Services\Telegram\TelegramMessages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class SystemAlertService
{
    public function __construct(private readonly PatientAccessService $access)
    {
    }

    public function listForUser(User $user, array $filters = [])
    {
        $query = $this->queryForUser($user)->latest();

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'unread') {
                $query->unread()->active();
            } elseif ($filters['status'] === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($filters['status'] === 'acknowledged') {
                $query->where('status', SystemAlert::STATUS_ACKNOWLEDGED);
            } elseif ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === SystemAlert::STATUS_RESOLVED) {
                $query->where('status', SystemAlert::STATUS_RESOLVED);
            }
        }

        if (! empty($filters['severity']) && in_array($filters['severity'], SystemAlert::SEVERITIES, true)) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['category']) && in_array($filters['category'], SystemAlert::CATEGORIES, true)) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->paginate($perPage);
    }

    public function unreadCountForUser(User $user): int
    {
        return $this->queryForUser($user)->active()->unread()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user): array
    {
        $base = $this->queryForUser($user);

        return [
            'active' => (clone $base)->active()->count(),
            'unread' => (clone $base)->active()->unread()->count(),
            'critical' => (clone $base)->active()->where('severity', SystemAlert::SEVERITY_CRITICAL)->count(),
            'warning' => (clone $base)->active()->where('severity', SystemAlert::SEVERITY_WARNING)->count(),
            'acknowledged' => (clone $base)->where('status', SystemAlert::STATUS_ACKNOWLEDGED)->count(),
            'resolved' => (clone $base)->where('status', SystemAlert::STATUS_RESOLVED)->count(),
            'by_category' => collect(SystemAlert::visibleCategoriesFor($user))
                ->mapWithKeys(fn (string $category) => [
                    $category => (clone $base)->active()->where('category', $category)->count(),
                ])
                ->all(),
        ];
    }

    public function markRead(SystemAlert $alert): SystemAlert
    {
        if ($alert->read_at === null) {
            $alert->update(['read_at' => Carbon::now()]);
            $this->broadcastChange();
        }

        return $alert->fresh();
    }

    public function markAllRead(User $user): int
    {
        $count = $this->queryForUser($user)->unread()->update(['read_at' => Carbon::now()]);

        if ($count > 0) {
            $this->broadcastChange();
        }

        return $count;
    }

    public function acknowledge(SystemAlert $alert, User $user): SystemAlert
    {
        if ($alert->status === SystemAlert::STATUS_RESOLVED) {
            return $alert->fresh();
        }

        $alert->update([
            'status' => SystemAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => Carbon::now(),
            'read_at' => $alert->read_at ?? Carbon::now(),
        ]);

        $this->broadcastChange();

        return $alert->fresh();
    }

    public function resolve(SystemAlert $alert, User $user): SystemAlert
    {
        if ($alert->status === SystemAlert::STATUS_RESOLVED) {
            return $alert->fresh();
        }

        $alert->update([
            'status' => SystemAlert::STATUS_RESOLVED,
            'resolved_by' => $user->id,
            'resolved_at' => Carbon::now(),
            'read_at' => $alert->read_at ?? Carbon::now(),
            'data' => array_merge($alert->data ?? [], [
                'resolved_source' => 'manual',
                'resolved_by_id' => $user->id,
            ]),
        ]);

        $this->broadcastChange();

        return $alert->fresh();
    }

    /**
     * Create or refresh an alert for a stable source key.
     *
     * The source key is the idempotency boundary: repeated detector runs do not
     * create duplicates and, when nothing material changed, do not touch the
     * row either. A condition that returns after a system resolution reopens the
     * original alert; a manual resolution is respected for the configured
     * snooze window.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function ensureAlert(array $attributes): SystemAlert
    {
        $sourceKey = trim((string) ($attributes['source_key'] ?? ''));

        if ($sourceKey === '') {
            throw new InvalidArgumentException('A system alert requires a source_key.');
        }

        $payload = Arr::only($attributes, [
            'category',
            'patient_id',
            'severity',
            'title',
            'message',
            'module',
            'url',
            'data',
        ]);

        $alert = SystemAlert::query()->where('source_key', $sourceKey)->first();

        if ($alert === null) {
            try {
                $alert = SystemAlert::query()->create(array_merge($payload, [
                    'source_key' => $sourceKey,
                    'status' => SystemAlert::STATUS_ACTIVE,
                    'read_at' => null,
                ]));

                $this->broadcastChange();
                $this->notifyTelegram($alert);

                return $alert;
            } catch (QueryException $exception) {
                // Another worker inserted the same deterministic key first.
                $alert = SystemAlert::query()->where('source_key', $sourceKey)->first();

                if ($alert === null) {
                    throw $exception;
                }
            }
        }

        if ($alert->status === SystemAlert::STATUS_RESOLVED) {
            if (! $this->shouldReopen($alert)) {
                return $alert;
            }

            $alert->fill($payload);
            $alert->forceFill([
                'status' => SystemAlert::STATUS_ACTIVE,
                'read_at' => null,
                'acknowledged_by' => null,
                'acknowledged_at' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'data' => Arr::except($payload['data'] ?? [], [
                    'resolved_source',
                    'resolved_by_id',
                    'resolved_at',
                ]),
            ]);
            $alert->save();

            $this->broadcastChange();

            return $alert;
        }

        $alert->fill($payload)->fill(['status' => $alert->status]);

        if ($alert->isDirty()) {
            $alert->save();
            $this->broadcastChange();
        }

        return $alert;
    }

    /**
     * Resolve alerts whose deterministic source keys were not seen by a
     * successful detector scan.
     *
     * @param  array<int, string>  $seenKeys
     */
    public function resolveMissing(string $prefix, array $seenKeys): int
    {
        $seenKeys = array_values(array_unique(array_filter($seenKeys)));
        $resolved = 0;

        SystemAlert::query()
            ->where('source_key', 'like', $prefix.'%')
            ->active()
            ->when($seenKeys !== [], fn (Builder $query) => $query->whereNotIn('source_key', $seenKeys))
            ->orderBy('id')
            ->chunkById(200, function ($alerts) use (&$resolved) {
                foreach ($alerts as $alert) {
                    $alert->update([
                        'status' => SystemAlert::STATUS_RESOLVED,
                        'resolved_by' => null,
                        'resolved_at' => Carbon::now(),
                        'read_at' => $alert->read_at ?? Carbon::now(),
                        'data' => array_merge($alert->data ?? [], [
                            'resolved_source' => 'system',
                            'resolved_at' => Carbon::now()->toIso8601String(),
                        ]),
                    ]);

                    $resolved++;
                }
            });

        if ($resolved > 0) {
            $this->broadcastChange();
        }

        return $resolved;
    }

    private function queryForUser(User $user): Builder
    {
        $query = SystemAlert::query()->forCategories(SystemAlert::visibleCategoriesFor($user));

        if ($this->access->isRestricted($user)) {
            $query->whereIn('patient_id', $this->access->assignedPatientIds($user));
        }

        return $query;
    }

    private function shouldReopen(SystemAlert $alert): bool
    {
        if (($alert->data['resolved_source'] ?? 'system') !== 'manual') {
            return true;
        }

        $snoozeMinutes = max(0, (int) config('alerts.reopen.manual_snooze_minutes', 1440));

        return $alert->resolved_at === null
            || $alert->resolved_at->lte(Carbon::now()->subMinutes($snoozeMinutes));
    }

    private function notifyTelegram(SystemAlert $alert): void
    {
        if (! config('alerts.telegram.enabled', false)) {
            return;
        }

        // Never send patient-linked or financial alerts to a group chat.
        if ($alert->patient_id !== null) {
            return;
        }

        if (! in_array($alert->category, (array) config('alerts.telegram.categories', []), true)) {
            return;
        }

        $minimum = (string) config('alerts.telegram.minimum_severity', SystemAlert::SEVERITY_CRITICAL);
        $minimumRank = SystemAlert::SEVERITY_RANK[$minimum] ?? SystemAlert::SEVERITY_RANK[SystemAlert::SEVERITY_CRITICAL];
        $severityRank = SystemAlert::SEVERITY_RANK[$alert->severity] ?? 0;

        if ($severityRank < $minimumRank) {
            return;
        }

        try {
            $group = TelegramGroupConfig::current();

            if (! $group->allows(TelegramGroupConfig::CATEGORY_SYSTEM_ALERTS)) {
                return;
            }

            SendTelegramMessage::dispatch(
                $group->chat_id,
                TelegramMessages::systemAlert($alert->severity, $alert->title),
                null,
                'system_alert',
                $alert->title,
                'system-alert:'.$alert->id,
            );
        } catch (Throwable $exception) {
            Log::warning('System alert Telegram delivery could not be queued.', [
                'alert_id' => $alert->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function broadcastChange(): void
    {
        try {
            SystemAlertsUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('System alert broadcast could not be dispatched.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
