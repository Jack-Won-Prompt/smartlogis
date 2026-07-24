<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SettlementItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 정산 명세. 사용분 항목 1건이 매출/매입 각 1행으로 반영된다.
 *
 * @property int $id
 * @property int $settlement_id
 * @property int $usage_report_item_id
 * @property int $qty
 * @property string $amount
 */
class SettlementItem extends Model
{
    /** @use HasFactory<SettlementItemFactory> */
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'usage_report_item_id',
        'product_id',
        'qty',
        'unit_price',
        'amount',
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

    /** @return BelongsTo<Settlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    /** @return BelongsTo<UsageReportItem, $this> */
    public function usageReportItem(): BelongsTo
    {
        return $this->belongsTo(UsageReportItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
