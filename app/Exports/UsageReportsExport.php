<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\UsageReport;
use Illuminate\Support\Carbon;

/** @extends BaseQueryExport<UsageReport> */
class UsageReportsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['사용분번호', '병원', '상태', '사용일', '품목수', '합계금액', '전송일시', '승인일시'];
    }

    /**
     * @param  UsageReport  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->report_no,
            $row->hospital->name,
            $row->status->label(),
            $row->usage_date->toDateString(),
            $row->items_count ?? $row->items->count(),
            (float) $row->total_amount,
            $row->submitted_at ? Carbon::parse($row->submitted_at)->timezone('Asia/Seoul')->format('Y-m-d H:i') : null,
            $row->approved_at ? Carbon::parse($row->approved_at)->timezone('Asia/Seoul')->format('Y-m-d H:i') : null,
        ];
    }
}
