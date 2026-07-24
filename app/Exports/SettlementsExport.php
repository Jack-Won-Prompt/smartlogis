<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Settlement;
use Illuminate\Support\Carbon;

/** @extends BaseQueryExport<Settlement> */
class SettlementsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['정산월', '거래처', '구분', '상태', '총수량', '총금액', '확정일시'];
    }

    /**
     * @param  Settlement  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->year_month,
            $row->organization->name,
            $row->settle_type->label(),
            $row->status->label(),
            $row->total_qty,
            (float) $row->total_amount,
            $row->confirmed_at ? Carbon::parse($row->confirmed_at)->timezone('Asia/Seoul')->format('Y-m-d H:i') : null,
        ];
    }
}
