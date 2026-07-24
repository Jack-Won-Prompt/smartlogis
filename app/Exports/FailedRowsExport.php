<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\ExcelFailReport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * 업로드 실패 행만 재다운로드 (CLAUDE.md §7.5).
 * 원본 컬럼 헤더 + "행번호" + "실패사유" 를 붙여 사용자가 수정 후 재업로드할 수 있게 한다.
 */
class FailedRowsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    use Exportable;

    /**
     * @param  array<int, string>  $columns  원본 컬럼 헤더(한국어)
     */
    public function __construct(
        private readonly ExcelFailReport $report,
        private readonly array $columns,
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['행번호', ...$this->columns, '실패사유'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(function (array $f): array {
            $values = [];
            foreach ($this->columns as $col) {
                $values[] = $f['data'][$col] ?? '';
            }

            return [$f['row'], ...$values, $f['reason']];
        }, $this->report->failures());
    }
}
