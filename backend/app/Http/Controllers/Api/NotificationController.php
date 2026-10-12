<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        $user = $request->user();

        $paginated = $user->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification) => $this->serialize($notification));

        return response()->json($paginated);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        $user = $request->user();

        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        $request->user()->notifications()->whereKey($notification)->delete();

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.view');

        return response()->json([
            'data' => $this->serializePreferences(
                NotificationPreference::forUser($request->user()),
            ),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $this->authorizeAccess($request, 'notifications.manage');

        $validated = $request->validate([
            'new_message' => ['sometimes', 'boolean'],
            'group_updates' => ['sometimes', 'boolean'],
            'system_announcements' => ['sometimes', 'boolean'],
            'telegram' => ['sometimes', 'boolean'],
        ]);

        $preferences = NotificationPreference::forUser($request->user());

        if ($validated !== []) {
            $preferences->update($validated);
        }

        return response()->json([
            'message' => 'Notification preferences updated.',
            'data' => $this->serializePreferences($preferences->fresh()),
        ]);
    }

    private function authorizeAccess(Request $request, string $ability): void
    {
        abort_unless($request->user()?->can($ability), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePreferences(NotificationPreference $preferences): array
    {
        return [
            'new_message' => (bool) $preferences->new_message,
            'group_updates' => (bool) $preferences->group_updates,
            'system_announcements' => (bool) $preferences->system_announcements,
            'telegram' => (bool) $preferences->telegram,
        ];
    }
}
