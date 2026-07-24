<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * 리스트 화면 공통 Export 베이스 (CLAUDE.md §7.5).
 * 컨트롤러/Livewire 의 동일 필터 쿼리를 그대로 주입받아 FromQuery + chunk 로 내보낸다.
 *
 * 서브클래스는 headings() 와 map() 만 구현한다.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @implements WithMapping<TModel>
 */
abstract class BaseQueryExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  Builder<TModel>  $query  화면과 동일한 필터가 적용된 쿼리
     */
    public function __construct(protected Builder $query) {}

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * 헤더 행(한국어).
     *
     * @return array<int, string>
     */
    abstract public function headings(): array;

    /**
     * 한 행 매핑.
     *
     * @param  TModel  $row
     * @return array<int, mixed>
     */
    abstract public function map($row): array;
}
