<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementPosterStorage;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly AnnouncementPosterStorage $posters,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageAny', Announcement::class);

        $query = Announcement::query()->with('author:id,name')->withCount('reads');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($audience = $request->query('audience')) {
            $query->where('audience', $audience);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $this->applyStatusFilter($query, $request->query('status'));

        $announcements = $query->latest()->paginate(15);

        return response()->json(array_merge($announcements->toArray(), [
            'stats' => $this->announcements->stats(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $validated = $request->validate($this->rules(creating: true));
        $this->assertExpiryAfterPublication($validated);

        $state = $this->resolvePublication($validated);

        unset($validated['poster'], $validated['remove_poster']);

        $announcement = Announcement::create(array_merge($validated, [
            'created_by' => $request->user()->id,
            'poster_path' => $request->hasFile('poster') ? $this->storePoster($request->file('poster')) : null,
            'is_published' => false,
            'published_at' => $state['published_at'],
        ]));

        if ($state['publish']) {
            $this->announcements->publish($announcement);
        }

        return response()->json([
            'message' => 'Announcement created successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return response()->json([
            'data' => $announcement->load('author:id,name')->loadCount('reads'),
        ]);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $validated = $request->validate($this->rules());
        $this->assertExpiryAfterPublication($validated);

        unset($validated['poster'], $validated['remove_poster']);

        if ($request->hasFile('poster')) {
            $this->deletePoster($announcement);
            $validated['poster_path'] = $this->storePoster($request->file('poster'));
        } elseif ($request->boolean('remove_poster')) {
            $this->deletePoster($announcement);
            $validated['poster_path'] = null;
        }

        $touchedState = $request->hasAny(['is_published', 'published_at']);
        $publishNow = false;

        if ($touchedState) {
            $state = $this->resolvePublication($validated);
            $publishNow = $state['publish'];

            unset($validated['is_published'], $validated['published_at']);

            if (! $publishNow) {
                $announcement->is_published = false;
                $announcement->published_at = $state['published_at'];
            }
        } else {
            unset($validated['is_published'], $validated['published_at']);
        }

        $announcement->fill($validated)->save();

        if ($publishNow) {
            $this->announcements->publish($announcement);
        }

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'data' => $announcement->fresh('author:id,name')->loadCount('reads'),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $this->deletePoster($announcement);
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function poster(Announcement $announcement): Response
    {
        $path = $announcement->poster_path;

        abort_if($path === null, 404);

        if ($this->posters->isExternal($path)) {
            return redirect()->away($path);
        }

        $response = $this->posters->inlineResponse($path);

        abort_if($response === null, 404);

        return $response;
    }

    public function publish(Announcement $announcement): JsonResponse
    {
        $this->authorize('publish', $announcement);

        $this->announcements->publish($announcement);

        return response()->json([
            'message' => 'Announcement published successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function unpublish(Announcement $announcement): JsonResponse
    {
        $this->authorize('publish', $announcement);

        $announcement->update(['is_published' => false]);

        return response()->json([
            'message' => 'Announcement unpublished successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function schedule(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('publish', $announcement);

        $validated = $request->validate([
            'published_at' => ['required', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (
            ! empty($validated['expires_at'])
            && strtotime((string) $validated['expires_at']) <= strtotime((string) $validated['published_at'])
        ) {
            throw ValidationException::withMessages([
                'expires_at' => 'The expiry date must be after the scheduled publication date.',
            ]);
        }

        $announcement->update([
            'is_published' => false,
            'published_at' => $validated['published_at'],
            'expires_at' => $validated['expires_at'] ?? null,
            'archived_at' => null,
        ]);

        return response()->json([
            'message' => 'Announcement scheduled successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function archive(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->update(['archived_at' => now()]);

        return response()->json([
            'message' => 'Announcement archived successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function restore(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->update(['archived_at' => null]);

        return response()->json([
            'message' => 'Announcement restored successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function readership(Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        return response()->json([
            'data' => $this->announcements->readership($announcement),
        ]);
    }

    public function audit(Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $entries = ActivityLog::query()
            ->where('subject_type', Announcement::class)
            ->where('subject_id', $announcement->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'actor' => $log->user_name,
                'actor_role' => $log->user_role,
                'changes' => $log->properties['changes'] ?? null,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json($entries);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{publish: bool, published_at: string|null}
     */
    private function resolvePublication(array $validated): array
    {
        $publishNow = (bool) ($validated['is_published'] ?? false);
        $publishedAt = $validated['published_at'] ?? null;

        if ($publishNow) {
            return ['publish' => true, 'published_at' => null];
        }

        if ($publishedAt !== null && strtotime((string) $publishedAt) > now()->getTimestamp()) {
            return ['publish' => false, 'published_at' => $publishedAt];
        }

        return ['publish' => false, 'published_at' => null];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertExpiryAfterPublication(array $validated): void
    {
        if (empty($validated['expires_at'])) {
            return;
        }

        $publishAt = $validated['published_at'] ?? null;

        if ($publishAt !== null && strtotime((string) $validated['expires_at']) <= strtotime((string) $publishAt)) {
            throw ValidationException::withMessages([
                'expires_at' => 'The expiry date must be after the publication date.',
            ]);
        }
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $now = now();

        match ($status) {
            'published' => $query->where('is_published', true)
                ->whereNull('archived_at')
                ->where(fn (Builder $builder): Builder => $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->where(fn (Builder $builder): Builder => $builder->whereNull('published_at')->orWhere('published_at', '<=', $now)),
            'draft' => $query->where('is_published', false)
                ->whereNull('archived_at')
                ->where(fn (Builder $builder): Builder => $builder->whereNull('published_at')->orWhere('published_at', '<=', $now)),
            'scheduled' => $query->where('is_published', false)
                ->whereNull('archived_at')
                ->whereNotNull('published_at')
                ->where('published_at', '>', $now),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', $now),
            'archived' => $query->whereNotNull('archived_at'),
            default => null,
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'body' => [$presence, 'string'],
            'category' => ['nullable', Rule::in(Announcement::CATEGORIES)],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('documents.max_size_kb', 10240)],
            'remove_poster' => ['sometimes', 'boolean'],
            'audience' => [$presence, Rule::in(Announcement::AUDIENCES)],
            'priority' => ['sometimes', Rule::in(Announcement::PRIORITIES)],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    private function storePoster(UploadedFile $file): string
    {
        return $this->posters->store($file);
    }

    private function deletePoster(Announcement $announcement): void
    {
        $this->posters->delete($announcement->poster_path);
    }
}
