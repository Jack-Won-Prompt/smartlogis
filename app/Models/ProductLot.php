<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\SupplierProductScope;
use Database\Factories\ProductLotFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 제품 × Lot × 유통기한. 재고/이동/추적의 최소 단위.
 *
 * @property int $id
 * @property int $product_id
 * @property string $lot_no
 * @property Carbon|null $expiry_date
 * @property-read Product $product
 */
#[ScopedBy(SupplierProductScope::class)]
class ProductLot extends Model
{
    /** @use HasFactory<ProductLotFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'lot_no',
        'expiry_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 오늘 기준 만료까지 남은 일수. 유통기한 없는 Lot 은 null.
     */
    public function daysToExpiry(?Carbon $from = null): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        // diffInDays 는 float 를 반환하므로 일수 정수로 내림 처리한다.
        return (int) ($from ?? Carbon::today())->diffInDays($this->expiry_date, false);
    }

    public function isExpired(?Carbon $from = null): bool
    {
        $days = $this->daysToExpiry($from);

        return $days !== null && $days < 0;
    }

    /**
     * FEFO 정렬: 유통기한 빠른 순, 기한 없는 Lot 은 맨 뒤.
     *
     * @param  Builder<ProductLot>  $query
     * @return Builder<ProductLot>
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    /**
     * D-day 이내 만료(경과분 포함) Lot.
     *
     * @param  Builder<ProductLot>  $query
     * @return Builder<ProductLot>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays($days));
    }
}
