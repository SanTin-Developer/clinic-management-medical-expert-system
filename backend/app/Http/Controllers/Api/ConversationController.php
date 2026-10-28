<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TelegramDeliveryLog;
use App\Models\User;
use App\Services\Communication\ConversationAttachmentService;
use App\Services\Communication\ConversationMessageSerializer;
use App\Services\Communication\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ConversationAttachmentService $attachments,
        private readonly ConversationMessageSerializer $messages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $user = $request->user();

        $paginated = Conversation::query()
            ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'activeParticipants.user:id,name,username',
                'messages' => fn ($query) => $query->latest('id')->limit(1)->with('sender:id,name'),
            ])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->through(fn (Conversation $conversation) => $this->serialize($conversation, $user));

        return response()->json($paginated);
    }

    public function recipients(Request $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        $viewer = $request->user();

        $users = User::query()
            ->whereKeyNot($viewer->id)
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'Patient'))
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->roles->first()?->name,
            ]);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        $validated = $request->validate([
            'type' => ['required', 'in:private,group'],
            'user_id' => ['required_if:type,private', 'integer', 'exists:users,id'],
            'title' => ['required_if:type,group', 'nullable', 'string', 'max:120'],
            'participant_ids' => ['required_if:type,group', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ]);

        $conversation = $validated['type'] === Conversation::TYPE_GROUP
            ? $this->conversations->createGroup(
                $request->user(),
                (string) $validated['title'],
                $validated['participant_ids'] ?? [],
            )
            : $this->conversations->findOrCreatePrivate(
                $request->user(),
                (int) $validated['user_id'],
            );

        $conversation->load('activeParticipants.user:id,name,username');

        return response()->json([
            'message' => 'Conversation ready.',
            'data' => $this->serialize($conversation, $request->user()),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load('activeParticipants.user:id,name,username');

        return response()->json([
            'data' => $this->serialize($conversation, $request->user()),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $paginated = $conversation->messages()
            ->with(['sender:id,name,username', 'deletedByUsers'])
            ->whereDoesntHave('deletedByUsers', fn ($q) => $q->where('users.id', $user->id))
            ->orderByDesc('id')
            ->paginate((int) config('communication.chat.messages_per_page', 30))
            ->through(fn (ConversationMessage $message) => $this->messages->toArray($message));

        return response()->json($paginated);
    }

    public function messageDelivery(Request $request, Conversation $conversation, ConversationMessage $message): JsonResponse
    {
        $this->authorize('view', $conversation);

        abort_unless($message->conversation_id === $conversation->id, 404);

        $logs = TelegramDeliveryLog::query()
            ->where('conversation_message_id', $message->id)
            ->orderBy('id')
            ->get()
            ->map(fn (TelegramDeliveryLog $log) => [
                'recipient_user_id' => $log->user_id,
                'channel' => 'telegram',
                'status' => $log->status,
                'attempts' => $log->attempts,
                'updated_at' => $log->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $maxVideoKb = (int) config('communication.chat.attachments.max_video_size_kb', 51200);

        $validated = $request->validate([
            'body' => [
                'nullable',
                'string',
                'max:'.(int) config('communication.chat.max_message_length', 4000),
            ],
            'attachment' => ['nullable', 'file', 'max:'.$maxVideoKb],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('attachment');

        if ($body === '' && $file === null) {
            throw ValidationException::withMessages([
                'body' => ['Write a message or attach a file.'],
            ]);
        }

        $stored = $file !== null ? $this->attachments->store($file, $conversation) : null;

        try {
            $message = $this->conversations->sendMessage(
                $conversation,
                $request->user(),
                $body,
                $stored,
            );
        } catch (Throwable $exception) {
            if ($stored !== null) {
                $this->attachments->delete($stored['path']);
            }

            throw $exception;
        }

        $message->load('sender:id,name,username');

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->messages->toArray($message),
        ], 201);
    }

    public function updateMessage(Request $request, Conversation $conversation, ConversationMessage $message): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);
        abort_if((int) $message->sender_id !== (int) $request->user()->id, 403);
        abort_if($message->hasAttachment(), 422, 'Attachments cannot be edited.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) config('communication.chat.max_message_length', 4000)],
        ]);

        $body = trim((string) $validated['body']);
        abort_if($body === '', 422, 'Message cannot be empty.');

        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        $message->load('sender:id,name,username');

        return response()->json([
            'message' => 'Message updated.',
            'data' => $this->messages->toArray($message),
        ]);
    }

    public function deleteMessage(Request $request, Conversation $conversation, ConversationMessage $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);

        $validated = $request->validate([
            'scope' => ['required', 'in:me,everyone'],
        ]);

        $user = $request->user();

        if ($validated['scope'] === 'everyone') {
            abort_if((int) $message->sender_id !== (int) $user->id, 403);

            try {
                $client = app(\App\Services\Telegram\TelegramClient::class);
                if ($client->isConfigured()) {
                    $logs = \App\Models\TelegramDeliveryLog::query()
                        ->where('conversation_message_id', $message->id)
                        ->where('status', \App\Models\TelegramDeliveryLog::STATUS_SENT)
                        ->whereNotNull('telegram_message_id')
                        ->get();

                    foreach ($logs as $log) {
                        if (! $log->telegram_chat_id || ! $log->telegram_message_id) {
                            continue;
                        }
                        try {
                            $client->deleteMessage($log->telegram_chat_id, (int) $log->telegram_message_id);
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            $message->delete();

            return response()->json([
                'message' => 'Message deleted for everyone.',
            ]);
        }

        $message->deletedByUsers()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'message' => 'Message deleted for you.',
        ]);
    }

    public function bulkDeleteMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['integer'],
            'scope' => ['required', 'in:me,everyone'],
        ]);

        $user = $request->user();
        $ids = array_map('intval', $validated['message_ids']);

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('id', $ids)
            ->get();

        if ($validated['scope'] === 'everyone') {
            $client = null;
            try {
                $client = app(\App\Services\Telegram\TelegramClient::class);
            } catch (\Throwable) {
                // ignore
            }

            foreach ($messages as $message) {
                if ((int) $message->sender_id !== (int) $user->id) {
                    continue;
                }

                if ($client && $client->isConfigured()) {
                    try {
                        $logs = \App\Models\TelegramDeliveryLog::query()
                            ->where('conversation_message_id', $message->id)
                            ->where('status', \App\Models\TelegramDeliveryLog::STATUS_SENT)
                            ->whereNotNull('telegram_message_id')
                            ->get();

                        foreach ($logs as $log) {
                            if (! $log->telegram_chat_id || ! $log->telegram_message_id) {
                                continue;
                            }
                            try {
                                $client->deleteMessage($log->telegram_chat_id, (int) $log->telegram_message_id);
                            } catch (\Throwable) {
                                // ignore
                            }
                        }
                    } catch (\Throwable) {
                        // ignore
                    }
                }

                $message->delete();
            }

            return response()->json([
                'message' => 'Messages deleted for everyone.',
            ]);
        }

        foreach ($messages as $message) {
            $message->deletedByUsers()->syncWithoutDetaching([$user->id]);
        }

        return response()->json([
            'message' => 'Messages deleted for you.',
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'message_id' => ['nullable', 'integer', 'exists:conversation_messages,id'],
        ]);

        $this->conversations->markRead(
            $conversation,
            $request->user(),
            $validated['message_id'] ?? null,
        );

        return response()->json([
            'message' => 'Conversation marked as read.',
            'unread_count' => 0,
        ]);
    }

    public function addParticipants(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('manage', $conversation);

        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ]);

        DB::transaction(function () use ($conversation, $validated) {
            foreach ($validated['participant_ids'] as $id) {
                $this->conversations->addParticipant(
                    $conversation,
                    User::query()->findOrFail($id),
                );
            }
        });

        $conversation->load('activeParticipants.user:id,name,username');

        return response()->json([
            'message' => 'Participants added.',
            'data' => $this->serialize($conversation, $request->user()),
        ]);
    }

    public function removeParticipant(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $isSelf = $request->user()->is($user);

        abort_unless(
            $isSelf || $request->user()->can('manage', $conversation),
            403,
        );

        abort_unless($conversation->hasActiveParticipant($user), 404, 'User is not a participant.');

        $this->conversations->removeParticipant($conversation, $user);

        $conversation->load('activeParticipants.user:id,name,username');

        return response()->json([
            'message' => $isSelf ? 'You left the conversation.' : 'Participant removed.',
            'data' => $this->serialize($conversation, $request->user()),
        ]);
    }

    public function attachment(Request $request, Conversation $conversation, ConversationMessage $message): Response
    {
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);
        abort_unless($message->hasAttachment(), 404, 'Attachment not found.');

        $path = (string) $message->attachment_path;

        if (str_starts_with($path, 'http')) {
            $inline = ! $request->boolean('download')
                && in_array($message->attachment_kind, [
                    ConversationMessage::ATTACHMENT_IMAGE,
                    ConversationMessage::ATTACHMENT_VIDEO,
                ], true);

            $headers = [
                'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => $this->contentDisposition(
                    (string) ($message->attachment_name ?: 'attachment'),
                    $inline,
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ];

            return \Illuminate\Support\Facades\Response::make('', 302, array_merge($headers, ['Location' => $path]));
        }

        $disk = null;
        $diskName = null;
        foreach (array_unique([$this->attachments->disk(), 'local', 'public']) as $candidate) {
            if (! config("filesystems.disks.{$candidate}")) {
                continue;
            }

            try {
                if (Storage::disk($candidate)->exists($path)) {
                    $disk = Storage::disk($candidate);
                    $diskName = $candidate;
                    break;
                }
            } catch (\Throwable) {
                // Try the next candidate disk (e.g. legacy attachment).
            }
        }

        abort_unless($disk !== null && $diskName !== null, 404, 'Attachment not found.');

        $inline = ! $request->boolean('download')
            && in_array($message->attachment_kind, [
                ConversationMessage::ATTACHMENT_IMAGE,
                ConversationMessage::ATTACHMENT_VIDEO,
            ], true);

        $headers = [
            'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
            'Content-Disposition' => $this->contentDisposition(
                (string) ($message->attachment_name ?: 'attachment'),
                $inline,
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return response()->file($disk->path($path), $headers);
        }

        return $disk->response($path, $message->attachment_name, $headers, $inline ? 'inline' : 'attachment');
    }

    private function contentDisposition(string $name, bool $inline): string
    {
        $name = str_replace(['"', '\\', "\r", "\n"], '', $name);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'attachment';

        return ($inline ? 'inline' : 'attachment')
            .'; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Conversation $conversation, User $viewer): array
    {
        $participants = $conversation->relationLoaded('activeParticipants')
            ? $conversation->activeParticipants
            : $conversation->activeParticipants()->with('user:id,name,username')->get();

        $participantList = $participants
            ->filter(fn ($participant) => $participant->user !== null)
            ->map(fn ($participant) => [
                'id' => $participant->user->id,
                'name' => $participant->user->name,
                'username' => $participant->user->username,
                'last_read_message_id' => $participant->last_read_message_id,
            ])
            ->values();

        $lastMessage = $conversation->relationLoaded('messages')
            ? $conversation->messages->first()
            : $conversation->messages()->with('sender:id,name')->latest('id')->first();

        $title = $conversation->title;

        if (! $conversation->isGroup()) {
            $other = $participantList->first(fn (array $participant) => $participant['id'] !== $viewer->id);
            $title = $other['name'] ?? 'Direct message';
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'is_group' => $conversation->isGroup(),
            'title' => $title,
            'created_by' => $conversation->created_by,
            'participants' => $participantList,
            'unread_count' => $this->conversations->unreadCount($conversation, $viewer),
            'last_message' => $lastMessage ? $this->messages->toArray($lastMessage) : null,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

}
