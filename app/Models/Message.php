<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 채팅 메시지(텍스트 + 첨부파일 + 답장). 삭제는 soft tombstone(deleted_at).
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string|null $body
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property int|null $reply_to_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property-read User|null $sender
 * @property-read Conversation $conversation
 * @property-read Message|null $replyTo
 */
class Message extends Model
{
    /** @var list<string> */
    protected $fillable = ['conversation_id', 'sender_id', 'body', 'file_path', 'file_name', 'file_size', 'reply_to_id', 'edited_at', 'deleted_at'];

    /** @var array<string, string> */
    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function isImage(): bool
    {
        if (! $this->file_name) {
            return false;
        }

        return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $this->file_name);
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return '';
        }
        $kb = $this->file_size / 1024;

        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : round($kb, 0).' KB';
    }

    /**
     * 프런트/브로드캐스트 공용 페이로드.
     *
     * @return array<string, mixed>
     */
    public function toChatArray(): array
    {
        $this->loadMissing(['sender', 'replyTo.sender']);

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender?->name,
            'body' => $this->body,
            'file_url' => $this->fileUrl(),
            'file_name' => $this->file_name,
            'is_image' => $this->isImage(),
            'reply_to' => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'sender_name' => $this->replyTo->sender?->name,
                'body' => $this->replyTo->body,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id')->with('sender');
    }
}
