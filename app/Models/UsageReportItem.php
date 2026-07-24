<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UsageReportItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 사용분 명세(품목 × Lot × 수량 × 금액).
 *
 * @property int $id
 * @property int $usage_report_id
 * @property int $product_id
 * @property int $lot_id
 * @property int $qty
 * @property string $amount
 */
class UsageReportItem extends Model
{
    /** @use HasFactory<UsageReportItemFactory> */
    use HasFactory;

    protected $fillable = [
        'usage_report_id',
        'product_id',
        'lot_id',
        'qty',
        'unit_price',
        'amount',
        'dept',
        'procedure_info',
        'scanned_barcode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<UsageReport, $this> */
    public function usageReport(): BelongsTo
    {
        return $this->belongsTo(UsageReport::class);
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
