<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConversationMessage extends Model
{
    public const SOURCE_WEB = 'web';

    public const SOURCE_TELEGRAM = 'telegram';

    public const ATTACHMENT_IMAGE = 'image';

    public const ATTACHMENT_VIDEO = 'video';

    public const ATTACHMENT_FILE = 'file';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_kind',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'source',
        'telegram_chat_id',
        'telegram_message_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'telegram_message_id' => 'integer',
            'attachment_size' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /**
     * Short text used in conversation previews, notifications and Telegram.
     */
    public function previewText(int $limit = 120): string
    {
        if (filled($this->body)) {
            return Str::limit((string) $this->body, $limit);
        }

        $name = $this->attachment_name ? trim((string) $this->attachment_name) : null;
        $mime = $this->attachment_mime ? strtolower((string) $this->attachment_mime) : null;

        return match ($this->attachment_kind) {
            self::ATTACHMENT_IMAGE => $name !== null && $name !== ''
                ? (str_starts_with($name, 'http') ? 'Image attachment' : 'Image: '.$name)
                : 'Image attachment',
            self::ATTACHMENT_VIDEO => $name !== null && $name !== ''
                ? (str_starts_with($name, 'http') ? 'Video' : 'Video: '.$name)
                : 'Video',
            self::ATTACHMENT_FILE => $name !== null && $name !== ''
                ? (str_starts_with($name, 'http') ? ($this->fileTypeLabel($mime, null) ? 'File ('.$this->fileTypeLabel($mime, null).')' : 'File') : 'File: '.$name.($this->fileTypeLabel($mime, $name) ? ' ('.$this->fileTypeLabel($mime, $name).')' : ''))
                : ($this->fileTypeLabel($mime, null) ? 'File ('.$this->fileTypeLabel($mime, null).')' : 'File'),
            default => 'Attachment',
        };
    }

    private function fileTypeLabel(?string $mime, ?string $name): ?string
    {
        if ($mime) {
            if (str_contains($mime, 'pdf')) {
                return 'PDF';
            }
            if (str_contains($mime, 'word') || str_contains($mime, 'document')) {
                return 'DOC';
            }
            if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) {
                return 'XLS';
            }
            if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) {
                return 'PPT';
            }
            if (str_contains($mime, 'text/csv')) {
                return 'CSV';
            }
            if (str_contains($mime, 'text/plain')) {
                return 'TXT';
            }
        }

        if ($name) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            return match ($ext) {
                'pdf' => 'PDF',
                'doc', 'docx' => 'DOC',
                'xls', 'xlsx' => 'XLS',
                'ppt', 'pptx' => 'PPT',
                'csv' => 'CSV',
                'txt' => 'TXT',
                default => strtoupper($ext) ?: null,
            };
        }

        return null;
    }

    public function isFromTelegram(): bool
    {
        return $this->source === self::SOURCE_TELEGRAM;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationMessageRead::class, 'message_id');
    }

    public function deletedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_message_user_deletes');
    }
}

