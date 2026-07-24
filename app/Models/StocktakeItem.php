<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StocktakeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 실사 명세. diff_qty = counted_qty - system_qty.
 *
 * @property int $id
 * @property int $stocktake_id
 * @property int $lot_id
 * @property int $system_qty
 * @property int|null $counted_qty
 * @property int $diff_qty
 */
class StocktakeItem extends Model
{
    /** @use HasFactory<StocktakeItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stocktake_id',
        'product_id',
        'lot_id',
        'system_qty',
        'counted_qty',
        'diff_qty',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'diff_qty' => 'integer',
        ];
    }

    /** @return BelongsTo<Stocktake, $this> */
    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
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
