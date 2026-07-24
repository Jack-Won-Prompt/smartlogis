<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;

/**
 * 제품 마스터 Export — 화면 필터 쿼리를 그대로 사용 (CLAUDE.md §7.5).
 *
 * @extends BaseQueryExport<Product>
 */
class ProductsExport extends BaseQueryExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['제품코드', '제품명', 'GTIN', '보험코드', '규격', '제조사', '공급사', '단위', 'BOX당수량', '매입가', '매출가', '보관유형', '멸균', '사용여부'];
    }

    /**
     * @param  Product  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->product_code,
            $row->product_name,
            $row->gtin,
            $row->edi_code,
            $row->spec,
            $row->manufacturer,
            $row->supplier->name,
            $row->unit,
            $row->box_qty,
            (float) $row->purchase_price,
            (float) $row->sales_price,
            $row->storage_type->label(),
            $row->is_sterile ? 'Y' : 'N',
            $row->is_active ? '사용' : '중지',
        ];
    }
}
