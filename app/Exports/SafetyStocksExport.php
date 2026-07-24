<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\SafetyStock;

/**
 * 안전재고 Export.
 *
 * @extends BaseQueryExport<SafetyStock>
 */
class SafetyStocksExport extends BaseQueryExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['병원코드', '병원명', '제품코드', '제품명', '안전재고', '최대재고', '보충수량'];
    }

    /**
     * @param  SafetyStock  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->hospital?->code,
            $row->hospital?->name,
            $row->product?->product_code,
            $row->product?->product_name,
            $row->safety_qty,
            $row->max_qty,
            $row->reorder_qty,
        ];
    }
}
