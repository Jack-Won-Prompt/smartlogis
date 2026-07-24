<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InboundItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 입고 명세. 입고 확정 시 lot_no/expiry_date 로 ProductLot 을 찾거나 생성한다.
 *
 * @property int $id
 * @property int $inbound_id
 * @property int $product_id
 * @property string $lot_no
 * @property Carbon|null $expiry_date
 * @property int $qty
 * @property string|null $scanned_barcode
 */
class InboundItem extends Model
{
    /** @use HasFactory<InboundItemFactory> */
    use HasFactory;

    protected $fillable = [
        'inbound_id',
        'product_id',
        'lot_no',
        'expiry_date',
        'qty',
        'unit_price',
        'scanned_barcode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Inbound, $this> */
    public function inbound(): BelongsTo
    {
        return $this->belongsTo(Inbound::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
