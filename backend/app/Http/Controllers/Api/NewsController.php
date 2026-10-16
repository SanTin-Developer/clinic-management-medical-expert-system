<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();
        $query = $this->announcements->visibleTo($user)
            ->withExists(['reads as is_read' => fn (Builder $builder): Builder => $builder->where('user_id', $user->id)]);

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($from = $request->query('from')) {
            $query->where('published_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('published_at', '<=', $to.' 23:59:59');
        }

        if ($request->boolean('unread')) {
            $query->whereDoesntHave('reads', fn (Builder $builder): Builder => $builder->where('user_id', $user->id));
        }

        $page = $query
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 else 2 end")
            ->latest('published_at')
            ->paginate(12);

        $featured = $this->announcements->visibleTo($user)
            ->where('priority', 'urgent')
            ->latest('published_at')
            ->first();

        return response()->json(array_merge($page->toArray(), [
            'unread_count' => $this->announcements->unreadCount($user),
            'featured' => $featured,
        ]));
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();

        if (! $this->announcements->canView($user, $announcement)) {
            abort(404);
        }

        $this->announcements->markRead($user, $announcement);

        return response()->json([
            'data' => $announcement->load('author:id,name'),
            'is_read' => true,
            'unread_count' => $this->announcements->unreadCount($user),
        ]);
    }

    public function read(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();

        if (! $this->announcements->canView($user, $announcement)) {
            abort(404);
        }

        $this->announcements->markRead($user, $announcement);

        return response()->json([
            'message' => 'Announcement marked as read.',
            'unread_count' => $this->announcements->unreadCount($user),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();
        $this->announcements->markAllRead($user);

        return response()->json([
            'message' => 'All announcements marked as read.',
            'unread_count' => $this->announcements->unreadCount($user),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        return response()->json([
            'unread_count' => $this->announcements->unreadCount($request->user()),
        ]);
    }
}
