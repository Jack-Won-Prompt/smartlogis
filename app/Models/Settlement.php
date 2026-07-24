<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Enums\SettleType;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 월 정산서. (연월 × 조직 × 매출/매입) 단위로 하나만 존재한다.
 *
 * @property int $id
 * @property string $year_month
 * @property int $org_id
 * @property SettleType $settle_type
 * @property SettlementStatus $status
 * @property string $total_amount
 */
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    protected $fillable = [
        'year_month',
        'org_id',
        'settle_type',
        'status',
        'total_qty',
        'total_amount',
        'confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settle_type' => SettleType::class,
            'status' => SettlementStatus::class,
            'total_qty' => 'integer',
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /** @return HasMany<SettlementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    /**
     * @param  Builder<Settlement>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Settlement>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['year_month'] ?? null, fn (Builder $q, $v) => $q->where('year_month', $v))
            ->when($filters['org_id'] ?? null, fn (Builder $q, $v) => $q->where('org_id', $v))
            ->when($filters['settle_type'] ?? null, fn (Builder $q, $v) => $q->where('settle_type', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v));
    }
}
