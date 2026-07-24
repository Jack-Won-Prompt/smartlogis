<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OutboundItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 출고 명세. lot_id 는 피킹 시점에 StockService::allocateFefo() 가 배정한다.
 *
 * @property int $id
 * @property int $outbound_id
 * @property int $product_id
 * @property int|null $lot_id
 * @property int $qty
 */
class OutboundItem extends Model
{
    /** @use HasFactory<OutboundItemFactory> */
    use HasFactory;

    protected $fillable = [
        'outbound_id',
        'product_id',
        'lot_id',
        'qty',
        'unit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Outbound, $this> */
    public function outbound(): BelongsTo
    {
        return $this->belongsTo(Outbound::class);
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
