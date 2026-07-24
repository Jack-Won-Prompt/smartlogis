<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 문서번호 채번 (CLAUDE.md §8). 시퀀스 테이블 + lock 으로 동시성 안전하게 생성한다.
 * 예) IB-20260723-0001, OB-20260723-0007, ST-20260723-0002, UR-202607-SEOUL-0003
 */
class DocumentNoService
{
    /**
     * @param  string  $prefix  예: 'IB', 'OB', 'ST'
     * @param  string|null  $scope  날짜 외 추가 스코프(예: 병원코드)
     */
    public function next(string $prefix, ?string $scope = null, string $dateFormat = 'Ymd'): string
    {
        $datePart = Carbon::now()->format($dateFormat);
        $key = $scope !== null
            ? "{$prefix}-{$datePart}-{$scope}"
            : "{$prefix}-{$datePart}";

        return DB::transaction(function () use ($key, $prefix, $datePart, $scope) {
            $row = DB::table('document_sequences')->where('prefix', $key)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('document_sequences')->insert(['prefix' => $key, 'last_no' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $no = 1;
            } else {
                $no = $row->last_no + 1;
                DB::table('document_sequences')->where('prefix', $key)->update(['last_no' => $no, 'updated_at' => now()]);
            }

            $seq = str_pad((string) $no, 4, '0', STR_PAD_LEFT);

            return $scope !== null
                ? "{$prefix}-{$datePart}-{$scope}-{$seq}"
                : "{$prefix}-{$datePart}-{$seq}";
        });
    }
}
