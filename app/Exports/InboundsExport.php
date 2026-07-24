<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Inbound;

/** @extends BaseQueryExport<Inbound> */
class InboundsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['입고번호', '구분', '출발지', '도착지', '상태', '예정일', '확정일시', '품목수'];
    }

    /**
     * @param  Inbound  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->inbound_no,
            $row->direction->label(),
            $row->fromOrg?->name,
            $row->toOrg?->name,
            $row->status->label(),
            $row->planned_date?->toDateString(),
            $row->confirmed_at?->timezone('Asia/Seoul')->format('Y-m-d H:i'),
            $row->items_count ?? $row->items->count(),
        ];
    }
}
