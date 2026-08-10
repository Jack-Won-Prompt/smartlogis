<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 반납 품목(제품 × Lot × 수량).
 *
 * @property int $id
 * @property int $stock_return_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $qty
 */
class StockReturnItem extends Model
{
    protected $fillable = ['stock_return_id', 'product_id', 'lot_id', 'qty'];

    /** @return BelongsTo<StockReturn, $this> */
    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class);
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
}
