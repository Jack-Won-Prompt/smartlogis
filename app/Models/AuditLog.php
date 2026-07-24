<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 감사 로그. AuditLogObserver 가 자동 기록하며 수정/삭제하지 않는다.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string $entity
 * @property int|null $entity_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'before',
        'after',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 감사 로그 1건 기록. Observer·서비스(승인/마감 등) 공용 진입점.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        AuditAction $action,
        string $entity,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null,
    ): void {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before' => self::redact($before),
            'after' => self::redact($after),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * 민감 필드(비밀번호/토큰)는 감사 로그에 원문 저장하지 않는다.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private static function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach (['password', 'remember_token'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn (Builder $q, $v) => $q->where('action', $v))
            ->when($filters['entity'] ?? null, fn (Builder $q, $v) => $q->where('entity', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
