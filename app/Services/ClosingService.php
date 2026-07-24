<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\SettlementStatus;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\MonthlyClosing;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

/**
 * 월 마감 (CLAUDE.md §7.6). 마감된 연월의 데이터 생성·수정은 서버(FormRequest+Service)에서 차단한다.
 * 마감/마감 취소는 HQ 관리자만 수행하며 audit_logs 에 CLOSE/REOPEN 으로 기록된다.
 */
class ClosingService
{
    public function close(string $yearMonth, ?int $userId = null, ?string $memo = null): MonthlyClosing
    {
        if (MonthlyClosing::isClosed($yearMonth)) {
            throw DomainException::conflict("{$yearMonth} 은(는) 이미 마감되었습니다.");
        }

        return DB::transaction(function () use ($yearMonth, $userId, $memo) {
            $closing = MonthlyClosing::create([
                'year_month' => $yearMonth,
                'closed_at' => now(),
                'closed_by' => $userId,
                'memo' => $memo,
            ]);

            // 해당 월 정산서를 마감(CLOSED) 처리
            Settlement::where('year_month', $yearMonth)->update([
                'status' => SettlementStatus::CLOSED->value,
                'confirmed_at' => now(),
            ]);

            AuditLog::record(AuditAction::CLOSE, 'MonthlyClosing', null, null, ['year_month' => $yearMonth]);

            return $closing;
        });
    }

    public function reopen(string $yearMonth): void
    {
        $closing = MonthlyClosing::find($yearMonth);
        if ($closing === null) {
            throw new DomainException("{$yearMonth} 은(는) 마감되지 않았습니다.");
        }

        DB::transaction(function () use ($yearMonth, $closing) {
            $closing->delete();
            Settlement::where('year_month', $yearMonth)->update([
                'status' => SettlementStatus::OPEN->value,
                'confirmed_at' => null,
            ]);

            AuditLog::record(AuditAction::REOPEN, 'MonthlyClosing', null, ['year_month' => $yearMonth], null);
        });
    }
}
