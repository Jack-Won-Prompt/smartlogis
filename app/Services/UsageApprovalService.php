<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotiType;
use App\Enums\RefType;
use App\Enums\Severity;
use App\Enums\TxType;
use App\Enums\UsageStatus;
use App\Exceptions\DomainException;
use App\Models\MonthlyClosing;
use App\Models\Notification;
use App\Models\UsageReport;
use Illuminate\Support\Facades\DB;

/**
 * 사용분 승인 — 이 시스템에서 가장 중요한 트랜잭션 (CLAUDE.md §7.1).
 *
 * 1) 상태 검증(SUBMITTED 만 승인, 아니면 409)
 * 2) 마감월 차단(마감된 연월이면 거부)
 * 3) 각 항목 StockService::apply(USE, 음수) — 병원 재고 차감(부족 시 예외→전체 롤백)
 * 4) SettlementItem 생성(SALES: 병원 / PURCHASE: 공급사)
 * 5) 승인 완료 후 알림(트랜잭션 밖, afterCommit)
 */
class UsageApprovalService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly SettlementService $settlement,
        private readonly ReplenishmentService $replenishment,
    ) {}

    public function approve(UsageReport $report, ?int $userId = null): UsageReport
    {
        if (! $report->status->isApprovable()) {
            throw DomainException::conflict("승인할 수 없는 상태입니다: {$report->status->label()} (전송된 사용분만 승인 가능)");
        }

        $yearMonth = $report->yearMonth();
        if (MonthlyClosing::isClosed($yearMonth)) {
            throw new DomainException("마감된 {$yearMonth}월 사용분은 승인할 수 없습니다. 본사 관리자에게 마감 취소를 요청하세요.", 403);
        }

        $report->load(['items.product', 'hospital']);
        if ($report->items->isEmpty()) {
            throw new DomainException('사용분 명세가 없습니다.');
        }

        DB::transaction(function () use ($report, $yearMonth, $userId) {
            foreach ($report->items as $item) {
                // 병원 재고 차감(부족 시 DomainException → 전체 롤백)
                $this->stock->apply(
                    type: TxType::USE,
                    orgId: $report->hospital_id,
                    productId: $item->product_id,
                    lotId: $item->lot_id,
                    qty: -1 * (int) $item->qty,
                    refType: RefType::USAGE,
                    refId: $report->id,
                    unitPrice: (float) $item->unit_price,
                    memo: "사용분 {$report->report_no}",
                    createdBy: $userId,
                );

                // 매출/매입 정산 생성
                $this->settlement->postUsageItem(
                    $yearMonth,
                    $report->hospital_id,
                    (int) $item->product->supplier_id,
                    $item,
                );
            }

            $report->update([
                'status' => UsageStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);
        });

        // 승인 완료 알림 (afterCommit 성격 — 트랜잭션 밖)
        Notification::create([
            'noti_type' => NotiType::USAGE_SUBMITTED,
            'severity' => Severity::INFO,
            'target_org_id' => $report->hospital_id,
            'title' => '사용분 승인',
            'message' => "{$report->report_no} 이(가) 승인되어 재고가 차감되고 정산에 반영되었습니다.",
            'link_url' => '/usages',
            'is_read' => false,
        ]);

        // 승인으로 재차 안전재고 미달 발생 → 자동 보충 제안(§7.1-5)
        $this->replenishment->check($report->hospital_id);

        return $report->refresh();
    }

    public function reject(UsageReport $report, string $reason, ?int $userId = null): UsageReport
    {
        if (! $report->status->isApprovable()) {
            throw DomainException::conflict("반려할 수 없는 상태입니다: {$report->status->label()}");
        }

        $report->update([
            'status' => UsageStatus::REJECTED,
            'reject_reason' => $reason,
        ]);

        Notification::create([
            'noti_type' => NotiType::USAGE_REJECTED,
            'severity' => Severity::WARNING,
            'target_org_id' => $report->hospital_id,
            'title' => '사용분 반려',
            'message' => "{$report->report_no} 이(가) 반려되었습니다: {$reason}",
            'link_url' => '/usages',
            'is_read' => false,
        ]);

        return $report->refresh();
    }
}
