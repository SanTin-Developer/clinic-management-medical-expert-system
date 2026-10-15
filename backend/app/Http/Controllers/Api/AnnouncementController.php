<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query()->with('author:id,name');
        $base = Announcement::query();

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
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

        $this->applyStatusFilter($query, $request->query('status'));

        $announcements = $query->latest()->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->where('is_published', true)->count(),
            'draft' => (clone $base)->where('is_published', false)->count(),
            'urgent' => (clone $base)->where('priority', 'urgent')->count(),
        ];

        return response()->json(array_merge($announcements->toArray(), ['stats' => $stats]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $validated = $request->validate($this->rules(creating: true));

        $validated['created_by'] = $request->user()->id;

        if (! empty($validated['is_published'])) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        $announcement = Announcement::create($validated);

        return response()->json([
            'message' => 'Announcement created successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return response()->json([
            'data' => $announcement->load('author:id,name'),
        ]);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $validated = $request->validate($this->rules());

        if (! empty($validated['is_published']) && $announcement->published_at === null) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        $announcement->update($validated);

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'data' => $announcement->fresh('author:id,name'),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function publish(Announcement $announcement): JsonResponse
    {
        $this->authorize('publish', $announcement);

        $wasPublished = $announcement->is_published === true;

        $announcement->update([
            'is_published' => true,
            'published_at' => $announcement->published_at ?? now(),
        ]);

        if (! $wasPublished) {
            $this->notifyAudience($announcement);
        }

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

    private function notifyAudience(Announcement $announcement): void
    {
        $recipients = $this->recipientsFor($announcement);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new SystemAnnouncementNotification($announcement->title, $announcement->body, '/announcements'),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Announcement $announcement): Collection
    {
        $rolesByAudience = [
            'staff' => ['Doctor', 'Nurse', 'Head Nurse', 'Receptionist', 'Pharmacist', 'Admin'],
            'doctors' => ['Doctor'],
            'nurses' => ['Nurse', 'Head Nurse'],
            'receptionists' => ['Receptionist'],
            'pharmacists' => ['Pharmacist'],
            'patients' => ['Patient'],
        ];

        $query = User::query();

        if ($announcement->audience !== 'all') {
            $query->role($rolesByAudience[$announcement->audience] ?? []);
        }

        if ($announcement->created_by !== null) {
            $query->whereKeyNot($announcement->created_by);
        }

        return $query->get();
    }

    private function applyStatusFilter($query, ?string $status): void
    {
        match ($status) {
            'published' => $query->where('is_published', true)
                ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhere('expires_at', '>=', now())),
            'draft' => $query->where('is_published', false),
            'scheduled' => $query->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '>', now()),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
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
            'audience' => [$presence, Rule::in(Announcement::AUDIENCES)],
            'priority' => ['sometimes', Rule::in(Announcement::PRIORITIES)],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
