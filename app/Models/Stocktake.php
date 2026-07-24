<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StocktakeStatus;
use App\Models\Scopes\OrgLocationScope;
use Database\Factories\StocktakeFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 재고 실사. 확정 시 diff_qty 만큼 ADJUST 트랜잭션이 생성된다.
 *
 * @property int $id
 * @property string $stocktake_no
 * @property int $org_id
 * @property StocktakeStatus $status
 * @property Carbon|null $count_date
 * @property Carbon|null $confirmed_at
 */
#[ScopedBy(OrgLocationScope::class)]
class Stocktake extends Model
{
    /** @use HasFactory<StocktakeFactory> */
    use HasFactory;

    protected $fillable = [
        'stocktake_no',
        'org_id',
        'status',
        'count_date',
        'confirmed_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StocktakeStatus::class,
            'count_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /** @return HasMany<StocktakeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }

    /**
     * @param  Builder<Stocktake>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Stocktake>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['org_id'] ?? null, fn (Builder $q, $v) => $q->where('org_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('count_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('count_date', '<=', $v));
    }
}
