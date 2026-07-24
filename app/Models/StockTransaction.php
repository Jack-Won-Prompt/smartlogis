<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefType;
use App\Enums\TxType;
use App\Models\Scopes\OrgLocationScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 재고 원장. StockService 만 생성하며, 생성 후에는 수정/삭제하지 않는다.
 *
 * @property int $id
 * @property TxType $tx_type
 * @property int $org_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $qty
 * @property RefType|null $ref_type
 * @property int|null $ref_id
 */
#[ScopedBy(OrgLocationScope::class)]
class StockTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tx_type',
        'org_id',
        'product_id',
        'lot_id',
        'qty',
        'unit_price',
        'ref_type',
        'ref_id',
        'memo',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tx_type' => TxType::class,
            'ref_type' => RefType::class,
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'created_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lot 추적 화면용 필터.
     *
     * @param  Builder<StockTransaction>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<StockTransaction>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['org_id'] ?? null, fn (Builder $q, $v) => $q->where('org_id', $v))
            ->when($filters['product_id'] ?? null, fn (Builder $q, $v) => $q->where('product_id', $v))
            ->when($filters['lot_id'] ?? null, fn (Builder $q, $v) => $q->where('lot_id', $v))
            ->when($filters['tx_type'] ?? null, fn (Builder $q, $v) => $q->where('tx_type', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
