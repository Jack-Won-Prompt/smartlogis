<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use Database\Factories\InboundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 입고 문서. 공급사→창고(ASN)와 창고→병원 두 방향을 함께 처리한다.
 *
 * @property int $id
 * @property string $inbound_no
 * @property InboundDirection $direction
 * @property InboundStatus $status
 * @property int $from_org_id
 * @property int $to_org_id
 * @property Carbon|null $planned_date
 * @property Carbon|null $confirmed_at
 */
class Inbound extends Model
{
    /** @use HasFactory<InboundFactory> */
    use HasFactory;

    protected $fillable = [
        'inbound_no',
        'direction',
        'from_org_id',
        'to_org_id',
        'status',
        'planned_date',
        'confirmed_at',
        'outbound_id',
        'memo',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => InboundDirection::class,
            'status' => InboundStatus::class,
            'planned_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function fromOrg(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_org_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function toOrg(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_org_id');
    }

    /** @return BelongsTo<Outbound, $this> */
    public function outbound(): BelongsTo
    {
        return $this->belongsTo(Outbound::class);
    }

    /** @return HasMany<InboundItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InboundItem::class);
    }

    /**
     * @param  Builder<Inbound>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Inbound>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['direction'] ?? null, fn (Builder $q, $v) => $q->where('direction', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['from_org_id'] ?? null, fn (Builder $q, $v) => $q->where('from_org_id', $v))
            ->when($filters['to_org_id'] ?? null, fn (Builder $q, $v) => $q->where('to_org_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('planned_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('planned_date', '<=', $v))
            ->when($filters['keyword'] ?? null, fn (Builder $q, $v) => $q->where('inbound_no', 'like', "%{$v}%"));
    }
}
