<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Outbound;

/** @extends BaseQueryExport<Outbound> */
class OutboundsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['출고번호', '창고', '병원', '상태', '생성유형', '예정일', '출고일시', '배송완료', '품목수'];
    }

    /**
     * @param  Outbound  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->outbound_no,
            $row->warehouse?->name,
            $row->hospital?->name,
            $row->status->label(),
            $row->source_type->label(),
            $row->planned_date?->toDateString(),
            $row->shipped_at?->timezone('Asia/Seoul')->format('Y-m-d H:i'),
            $row->delivered_at?->timezone('Asia/Seoul')->format('Y-m-d H:i'),
            $row->items_count ?? $row->items->count(),
        ];
    }
}
