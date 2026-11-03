<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Admin inbox for messages submitted through the public Contact page.
 */
class WebsiteContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.view');

        $query = ContactMessage::query()->latest('created_at')->latest('id');

        if (($status = $request->query('status')) === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(20);

        return response()->json(array_merge($messages->toArray(), [
            'stats' => [
                'total' => ContactMessage::count(),
                'unread' => ContactMessage::unread()->count(),
                'read' => ContactMessage::read()->count(),
            ],
        ]));
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        $this->authorize('website.view');

        return response()->json(['data' => $contactMessage]);
    }

    public function markRead(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $this->authorize('website.contact.manage');

        $contactMessage->markAsRead();
        $this->syncNotifications($request, $contactMessage, true);

        return response()->json([
            'message' => 'Message marked as read.',
            'data' => $contactMessage->fresh(),
        ]);
    }

    public function markUnread(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $this->authorize('website.contact.manage');

        $contactMessage->markAsUnread();
        $this->syncNotifications($request, $contactMessage, false);

        return response()->json([
            'message' => 'Message marked as unread.',
            'data' => $contactMessage->fresh(),
        ]);
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $this->authorize('website.contact.manage');

        DatabaseNotification::query()
            ->where('data->kind', 'contact_message')
            ->where('data->contact_message_id', $contactMessage->id)
            ->delete();

        $contactMessage->delete();

        return response()->json(['message' => 'Message deleted successfully.']);
    }

    /**
     * Keep the acting administrator's bell entry in step with the inbox state.
     */
    private function syncNotifications(Request $request, ContactMessage $contactMessage, bool $read): void
    {
        $notifications = $request->user()->notifications()
            ->where('data->kind', 'contact_message')
            ->where('data->contact_message_id', $contactMessage->id)
            ->get();

        foreach ($notifications as $notification) {
            $read ? $notification->markAsRead() : $notification->markAsUnread();
        }
    }
}
