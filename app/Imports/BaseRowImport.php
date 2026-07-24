<?php

declare(strict_types=1);

namespace App\Imports;

use App\Support\ExcelFailReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * 리스트 화면 공통 Import 베이스 (CLAUDE.md §7.5).
 * WithHeadingRow 로 읽어 각 행을 rules() 로 검증하고, 성공 행만 storeRow() 로 저장한다.
 * 검증/저장 실패는 ExcelFailReport 에 행번호·사유와 함께 누적된다.
 *
 * 서브클래스 구현: rules(), attributes()(선택), storeRow(), columns().
 */
abstract class BaseRowImport implements ToCollection, WithHeadingRow
{
    use Importable;

    protected ExcelFailReport $report;

    public function __construct()
    {
        $this->report = new ExcelFailReport;
    }

    public function report(): ExcelFailReport
    {
        return $this->report;
    }

    /**
     * 행별 검증 규칙.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * 한국어 필드명(검증 메시지용).
     *
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [];
    }

    /**
     * 검증 통과 행 저장. 실패 시 예외를 던지면 해당 행이 실패로 기록된다.
     *
     * @param  array<string, mixed>  $row
     */
    abstract protected function storeRow(array $row): void;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $data = $row->toArray();
            $lineNo = $index + 2; // 헤더행(1) 다음부터

            $validator = Validator::make($data, $this->rules(), [], $this->attributes());

            if ($validator->fails()) {
                $this->report->addFailure($lineNo, implode(' ', $validator->errors()->all()), $data);

                continue;
            }

            try {
                $this->storeRow($validator->validated());
                $this->report->addSuccess();
            } catch (\Throwable $e) {
                $this->report->addFailure($lineNo, $e->getMessage(), $data);
            }
        }
    }
}
