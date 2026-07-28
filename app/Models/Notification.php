<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\Severity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 알림 센터 전용 자체 알림(Laravel 기본 notifications 미사용).
 *
 * @property int $id
 * @property NotiType $noti_type
 * @property Severity $severity
 * @property OrgType|null $target_role
 * @property int|null $target_org_id
 * @property bool $is_read
 */
class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'noti_type',
        'severity',
        'target_role',
        'target_org_id',
        'title',
        'message',
        'link_url',
        'is_read',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noti_type' => NotiType::class,
            'severity' => Severity::class,
            'target_role' => OrgType::class,
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function targetOrg(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'target_org_id');
    }

    /**
     * 특정 사용자가 볼 수 있는 알림(자기 조직 대상 + 자기 역할 대상).
     * HQ 는 전체를 본다.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isHq()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('target_org_id', $user->org_id)
                ->orWhere(function (Builder $sub) use ($user) {
                    $sub->whereNull('target_org_id')->where('target_role', $user->role);
                })
                // 전체 공지(대상 조직·역할 미지정) — 모든 사용자에게 노출
                ->orWhere(function (Builder $sub) {
                    $sub->whereNull('target_org_id')->whereNull('target_role');
                });
        });
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
