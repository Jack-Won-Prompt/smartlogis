<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 채팅 대화방(1:1 또는 그룹).
 *
 * @property int $id
 * @property string|null $name
 * @property bool $is_group
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $participants
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $lastMessage
 */
class Conversation extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'is_group'];

    /** @var array<string, string> */
    protected $casts = ['is_group' => 'boolean'];

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /** @return HasOne<Message, $this> */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** 두 사용자 간 기존 1:1 대화방 조회(없으면 null). */
    public static function findBetween(int $userA, int $userB): ?self
    {
        return self::query()
            ->where('is_group', false)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB))
            ->withCount('participants')
            ->having('participants_count', '=', 2)
            ->first();
    }

    public function unreadCount(int $userId): int
    {
        $lastRead = DB::table('conversation_user')
            ->where('conversation_id', $this->id)
            ->where('user_id', $userId)
            ->value('last_read_at');

        return $this->messages()
            ->whereNull('deleted_at')
            ->where('sender_id', '!=', $userId)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }

    public function displayName(int $userId): string
    {
        if ($this->is_group) {
            return $this->name ?: '그룹 채팅';
        }

        $other = $this->otherParticipant($userId);

        return $other !== null ? $other->name : '(알 수 없음)';
    }

    public function otherParticipant(int $userId): ?User
    {
        return $this->participants->firstWhere('id', '!=', $userId);
    }

    public function memberNames(int $userId, ?int $limit = 3): string
    {
        $names = $this->participants->where('id', '!=', $userId);
        if ($limit !== null) {
            $names = $names->take($limit);
        }

        return $names->pluck('name')->join(', ');
    }
}
