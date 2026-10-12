<?php

namespace App\Services\Communication;

use App\Events\MessageSent;
use App\Jobs\SendTelegramMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\NotificationPreference;
use App\Models\TelegramGroupConfig;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function findOrCreatePrivate(User $user, int $otherUserId): Conversation
    {
        $other = User::query()->findOrFail($otherUserId);

        if ($other->is($user)) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot start a conversation with yourself.',
            ]);
        }

        $existing = Conversation::query()
            ->where('type', Conversation::TYPE_PRIVATE)
            ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('activeParticipants', fn ($query) => $query->where('user_id', $other->id))
            ->withCount(['activeParticipants as active_count'])
            ->get()
            ->first(fn (Conversation $conversation) => $conversation->active_count === 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $other) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_PRIVATE,
                'created_by' => $user->id,
            ]);

            $this->addParticipant($conversation, $user);
            $this->addParticipant($conversation, $other);

            return $conversation;
        });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function createGroup(User $creator, string $title, array $userIds): Conversation
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $creator->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'A group needs at least one other member.',
            ]);
        }

        return DB::transaction(function () use ($creator, $title, $ids) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
                'created_by' => $creator->id,
            ]);

            $this->addParticipant($conversation, $creator);

            foreach ($ids as $id) {
                $this->addParticipant($conversation, User::query()->findOrFail($id));
            }

            return $conversation->fresh();
        });
    }

    public function addParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        $latestMessageId = $conversation->messages()->max('id');

        return ConversationParticipant::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'left_at' => null,
                'joined_at' => now(),
                'last_read_message_id' => $latestMessageId,
            ],
        );
    }

    public function removeParticipant(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['left_at' => now()]);
    }

    public function sendMessage(Conversation $conversation, User $sender, string $body): ConversationMessage
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->participants()
                ->where('user_id', $sender->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        $conversation->touch();

        event(new MessageSent($message));

        $this->notifyParticipants($conversation, $message, $sender);

        return $message;
    }

    public function markRead(Conversation $conversation, User $user, ?int $messageId = null): void
    {
        $messageId ??= $conversation->messages()->max('id');

        if ($messageId === null) {
            return;
        }

        $conversation->participants()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($messageId) {
                $query->whereNull('last_read_message_id')
                    ->orWhere('last_read_message_id', '<', $messageId);
            })
            ->update(['last_read_message_id' => $messageId]);
    }

    public function unreadCount(Conversation $conversation, User $user): int
    {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return 0;
        }

        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->when(
                $participant->last_read_message_id,
                fn ($query, $id) => $query->where('id', '>', $id),
            )
            ->count();
    }

    protected function notifyParticipants(
        Conversation $conversation,
        ConversationMessage $message,
        User $sender,
    ): void {
        $participants = $conversation->participants()
            ->whereNull('left_at')
            ->where('user_id', '!=', $sender->id)
            ->with('user')
            ->get();

        foreach ($participants as $participant) {
            $user = $participant->user;

            if (! $user) {
                continue;
            }

            $preferences = NotificationPreference::forUser($user);

            if ($preferences->allows('new_message')) {
                $user->notify(new NewMessageNotification(
                    conversationId: $conversation->id,
                    conversationTitle: $conversation->isGroup() ? $conversation->title : null,
                    messageId: $message->id,
                    senderName: $sender->name,
                    preview: Str::limit($message->body, 120),
                    isGroup: $conversation->isGroup(),
                ));
            }

            $this->dispatchUserTelegram($conversation, $message, $sender, $user, $preferences);
        }

        $this->dispatchGroupTelegram($conversation, $message, $sender);
    }

    private function dispatchUserTelegram(
        Conversation $conversation,
        ConversationMessage $message,
        User $sender,
        User $recipient,
        NotificationPreference $preferences,
    ): void {
        if (! $this->telegram->isConfigured() || ! $preferences->telegram) {
            return;
        }

        $connection = $recipient->telegramConnection;

        if (! $connection?->isVerified()) {
            return;
        }

        SendTelegramMessage::dispatch(
            chatId: $connection->telegram_chat_id,
            text: $this->telegramText($conversation, $message, $sender),
            userId: $recipient->id,
            eventType: 'new_message',
            subject: $conversation->isGroup() ? $conversation->title : $sender->name,
            idempotencyKey: 'message:'.$message->id.':user:'.$recipient->id,
        );
    }

    private function dispatchGroupTelegram(
        Conversation $conversation,
        ConversationMessage $message,
        User $sender,
    ): void {
        if (! $this->telegram->isConfigured()) {
            return;
        }

        $config = TelegramGroupConfig::current();

        if (! $config->allows(TelegramGroupConfig::CATEGORY_NEW_MESSAGE)) {
            return;
        }

        SendTelegramMessage::dispatch(
            chatId: $config->chat_id,
            text: $this->telegramText($conversation, $message, $sender),
            userId: null,
            eventType: 'new_message',
            subject: $conversation->isGroup() ? $conversation->title : 'Direct message',
            idempotencyKey: 'message:'.$message->id.':group',
        );
    }

    private function telegramText(Conversation $conversation, ConversationMessage $message, User $sender): string
    {
        $context = $conversation->isGroup()
            ? ' in <b>'.e((string) $conversation->title).'</b>'
            : '';

        return '<b>'.e($sender->name).'</b>'.$context.': '.e(Str::limit($message->body, 500));
    }
}
