<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\MonthlyClosing;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * 마감된 연월의 데이터 입력을 차단하는 검증 규칙 (CLAUDE.md §7.6).
 * 날짜(Y-m-d) 또는 연월(Y-m) 문자열을 받아 해당 월이 마감되었으면 실패시킨다.
 */
class NotClosedMonth implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $yearMonth = Carbon::parse($value)->format('Y-m');
        } catch (\Throwable) {
            return; // 날짜 형식 검증은 별도 규칙이 담당
        }

        if (MonthlyClosing::isClosed($yearMonth)) {
            $fail("마감된 {$yearMonth}월 데이터는 등록·수정할 수 없습니다. 본사 관리자에게 마감 취소를 요청하세요.");
        }
    }
}
