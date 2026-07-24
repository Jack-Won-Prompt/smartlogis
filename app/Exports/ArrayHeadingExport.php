<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * 헤더만 담긴 빈 템플릿 엑셀 (업로드 양식 다운로드용).
 */
class ArrayHeadingExport implements FromArray, ShouldAutoSize, WithHeadings
{
    use Exportable;

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows  예시 행(선택)
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
