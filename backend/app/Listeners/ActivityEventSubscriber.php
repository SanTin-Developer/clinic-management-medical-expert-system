<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records create/update/delete activity for every Eloquent model while a user
 * is authenticated. Internal/noisy models are skipped by ActivityLogger.
 *
 * The listener is best-effort: logging failures are reported but never bubble
 * up to the request that performed the change.
 */
class ActivityEventSubscriber
{
    /**
     * Guards against re-entrancy while the log entry itself is being written.
     */
    private static bool $recording = false;

    public function __construct(private readonly ActivityLogger $logger) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.created: *', [self::class, 'created']);
        $events->listen('eloquent.updated: *', [self::class, 'updated']);
        $events->listen('eloquent.deleted: *', [self::class, 'deleted']);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    public function created(string $eventName, array $data): void
    {
        $this->handle(ActivityLog::EVENT_CREATED, $data);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    public function updated(string $eventName, array $data): void
    {
        $this->handle(ActivityLog::EVENT_UPDATED, $data);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    public function deleted(string $eventName, array $data): void
    {
        $this->handle(ActivityLog::EVENT_DELETED, $data);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private function handle(string $event, array $data): void
    {
        $model = $data[0] ?? null;

        if (self::$recording || ! $model instanceof Model) {
            return;
        }

        if ($this->logger->isIgnoredModel($model::class)) {
            return;
        }

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        try {
            $properties = $this->propertiesFor($event, $model);

            if ($properties === null) {
                return;
            }

            self::$recording = true;

            $this->logger->record(
                $event,
                $this->describe($event, $model),
                $model,
                $properties,
                $user,
            );
        } catch (Throwable $e) {
            report($e);
        } finally {
            self::$recording = false;
        }
    }

    /**
     * @return array<string, mixed>|null Null when the change carries no
     *                                   meaningful information.
     */
    private function propertiesFor(string $event, Model $model): ?array
    {
        if ($event === ActivityLog::EVENT_UPDATED) {
            $changes = [];

            foreach ($model->getChanges() as $key => $value) {
                if ($this->logger->ignoredAttribute($key)) {
                    continue;
                }

                $changes[$key] = [
                    'from' => $this->logger->normalizeValue($model->getOriginal($key)),
                    'to' => $this->logger->normalizeValue($value),
                ];
            }

            return $changes === [] ? null : ['changes' => $changes];
        }

        $attributes = [];

        foreach ($model->getAttributes() as $key => $value) {
            if ($this->logger->ignoredAttribute($key)) {
                continue;
            }

            $attributes[$key] = $this->logger->normalizeValue($value);

            if (count($attributes) >= 25) {
                break;
            }
        }

        return $attributes === [] ? [] : ['attributes' => $attributes];
    }

    private function describe(string $event, Model $model): string
    {
        $label = Str::headline(class_basename($model));

        return match ($event) {
            ActivityLog::EVENT_CREATED => "Created {$label}",
            ActivityLog::EVENT_UPDATED => "Updated {$label}",
            ActivityLog::EVENT_DELETED => "Deleted {$label}",
            default => "{$event} {$label}",
        };
    }
}
