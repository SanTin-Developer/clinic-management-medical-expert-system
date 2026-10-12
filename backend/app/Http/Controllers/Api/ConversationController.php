<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Communication\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

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

        $paginated = $conversation->messages()
            ->with('sender:id,name,username')
            ->orderByDesc('id')
            ->paginate((int) config('communication.chat.messages_per_page', 30))
            ->through(fn (ConversationMessage $message) => $this->serializeMessage($message));

        return response()->json($paginated);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'max:'.(int) config('communication.chat.max_message_length', 4000),
            ],
        ]);

        $message = $this->conversations->sendMessage(
            $conversation,
            $request->user(),
            $validated['body'],
        );

        $message->load('sender:id,name,username');

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->serializeMessage($message),
        ], 201);
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
            'last_message' => $lastMessage ? $this->serializeMessage($lastMessage) : null,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ConversationMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender' => [
                'id' => $message->sender?->id,
                'name' => $message->sender?->name,
                'username' => $message->sender?->username,
            ],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
