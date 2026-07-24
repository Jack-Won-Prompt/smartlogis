<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\OrgLocationScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 현재고 캐시. 복합 PK(org_id, product_id, lot_id) 이므로 단건 save() 대신
 * StockService 의 upsert/lockForUpdate 경로로만 갱신한다(CLAUDE.md §4.1-1).
 *
 * @property int $org_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $qty
 */
#[ScopedBy(OrgLocationScope::class)]
class StockBalance extends Model
{
    protected $table = 'stock_balances';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected $fillable = [
        'org_id',
        'product_id',
        'lot_id',
        'qty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    /**
     * 재고가 남아 있는 행만.
     *
     * @param  Builder<StockBalance>  $query
     * @return Builder<StockBalance>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('qty', '>', 0);
    }

    /**
     * @param  Builder<StockBalance>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<StockBalance>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['org_id'] ?? null, fn (Builder $q, $v) => $q->where('org_id', $v))
            ->when($filters['product_id'] ?? null, fn (Builder $q, $v) => $q->where('product_id', $v))
            ->when($filters['keyword'] ?? null, function (Builder $q, string $keyword) {
                $q->whereHas('product', function (Builder $sub) use ($keyword) {
                    $sub->where('product_name', 'like', "%{$keyword}%")
                        ->orWhere('product_code', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['in_stock_only'] ?? null, fn (Builder $q) => $q->where('qty', '>', 0));
    }
}
