<?php

namespace App\Services\Communication;

use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Serializes a chat message consistently for API responses and broadcasts.
 */
class ConversationMessageSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ConversationMessage $message): array
    {
        $userId = Auth::id();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'source' => $message->source,
            'sender_id' => $message->sender_id,
            'sender' => [
                'id' => $message->sender?->id,
                'name' => $message->sender?->name,
                'username' => $message->sender?->username,
            ],
            'attachment' => $this->attachment($message),
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
            'can_edit' => $userId !== null && (int) $message->sender_id === (int) $userId && ! $message->hasAttachment(),
            'can_delete_everyone' => $userId !== null && (int) $message->sender_id === (int) $userId,
            'can_delete_me' => $userId !== null,
            'deleted_for_me' => $userId !== null ? $message->deletedByUsers()->where('users.id', $userId)->exists() : false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attachment(ConversationMessage $message): ?array
    {
        if (! $message->hasAttachment()) {
            return null;
        }

        $path = (string) $message->attachment_path;

        if (str_starts_with($path, 'http')) {
            return [
                'kind' => $message->attachment_kind,
                'name' => $message->attachment_name,
                'mime_type' => $message->attachment_mime,
                'size' => (int) $message->attachment_size,
                'url' => $path,
                'download_url' => $path,
            ];
        }

        $ttl = max(1, (int) config('communication.chat.attachments.signed_url_ttl_minutes', 360));
        $parameters = [
            'conversation' => $message->conversation_id,
            'message' => $message->id,
        ];

        $url = URL::temporarySignedRoute(
            'conversations.attachments.show',
            now()->addMinutes($ttl),
            $parameters,
            true,
        );
        $p = parse_url($url, PHP_URL_PATH);
        $q = parse_url($url, PHP_URL_QUERY);
        if ($p) {
            $url = $p.($q ? '?'.$q : '');
        }

        $downloadUrl = URL::temporarySignedRoute(
            'conversations.attachments.show',
            now()->addMinutes($ttl),
            [...$parameters, 'download' => 1],
            true,
        );
        $dp = parse_url($downloadUrl, PHP_URL_PATH);
        $dq = parse_url($downloadUrl, PHP_URL_QUERY);
        if ($dp) {
            $downloadUrl = $dp.($dq ? '?'.$dq : '');
        }

        return [
            'kind' => $message->attachment_kind,
            'name' => $message->attachment_name,
            'mime_type' => $message->attachment_mime,
            'size' => (int) $message->attachment_size,
            'url' => $url,
            'download_url' => $downloadUrl,
        ];
    }
}
