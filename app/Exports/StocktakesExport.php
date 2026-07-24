<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Stocktake;

/** @extends BaseQueryExport<Stocktake> */
class StocktakesExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['실사번호', '대상', '상태', '실사일', '확정일시', '품목수'];
    }

    /**
     * @param  Stocktake  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->stocktake_no,
            $row->organization->name,
            $row->status->label(),
            $row->count_date?->toDateString(),
            $row->confirmed_at?->timezone('Asia/Seoul')->format('Y-m-d H:i'),
            $row->items_count ?? $row->items->count(),
        ];
    }
}
