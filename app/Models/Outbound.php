<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Models\Scopes\HospitalScope;
use Database\Factories\OutboundFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * 출고 지시(창고 → 병원). 병원 계정은 자기 병원 건만 볼 수 있다.
 *
 * @property int $id
 * @property string $outbound_no
 * @property int $warehouse_id
 * @property int $hospital_id
 * @property OutboundStatus $status
 * @property OutboundSourceType $source_type
 * @property Carbon|null $planned_date
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property-read DeliveryProof|null $deliveryProof
 */
#[ScopedBy(HospitalScope::class)]
class Outbound extends Model
{
    /** @use HasFactory<OutboundFactory> */
    use HasFactory;

    protected $fillable = [
        'outbound_no',
        'warehouse_id',
        'hospital_id',
        'status',
        'source_type',
        'planned_date',
        'shipped_at',
        'delivered_at',
        'close_reminded_at',
        'memo',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutboundStatus::class,
            'source_type' => OutboundSourceType::class,
            'planned_date' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'close_reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'warehouse_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'hospital_id');
    }

    /** @return HasMany<OutboundItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OutboundItem::class);
    }

    /**
     * 배송 증빙(현장 사진 + 인수 서명). 출고 1건에 1건.
     *
     * @return HasOne<DeliveryProof, $this>
     */
    public function deliveryProof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    /**
     * @param  Builder<Outbound>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Outbound>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['hospital_id'] ?? null, fn (Builder $q, $v) => $q->where('hospital_id', $v))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $q, $v) => $q->where('warehouse_id', $v))
            ->when($filters['source_type'] ?? null, fn (Builder $q, $v) => $q->where('source_type', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('planned_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('planned_date', '<=', $v))
            ->when($filters['keyword'] ?? null, fn (Builder $q, $v) => $q->where('outbound_no', 'like', "%{$v}%"));
    }
}
