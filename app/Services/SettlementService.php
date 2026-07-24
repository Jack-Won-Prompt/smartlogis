<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettlementStatus;
use App\Enums\SettleType;
use App\Models\Settlement;
use App\Models\UsageReportItem;
use Illuminate\Support\Facades\DB;

/**
 * 월 정산 — 사용분 승인 시 매출(병원)/매입(공급사) 정산 항목을 생성한다.
 * (연월 × 조직 × 유형) 단위 정산서에 항목을 누적하며, 같은 사용분 항목은
 * settlement_items 의 unique(settlement_id, usage_report_item_id) 로 중복 반영을 막는다.
 */
class SettlementService
{
    /**
     * 사용분 항목 1건을 매출·매입 정산에 반영한다.
     * 반드시 승인 트랜잭션 안에서 호출.
     */
    public function postUsageItem(string $yearMonth, int $hospitalId, int $supplierId, UsageReportItem $item): void
    {
        $qty = (int) $item->qty;
        $salesAmount = (float) $item->amount;                          // 병원 매출(판매가 기준)
        $purchaseUnit = (float) ($item->product->purchase_price ?? 0);
        $purchaseAmount = $purchaseUnit * $qty;                        // 공급사 매입(매입가 기준)

        // 매출(SALES) — 병원
        $this->addItem($yearMonth, $hospitalId, SettleType::SALES, $item, $qty, (float) $item->unit_price, $salesAmount);

        // 매입(PURCHASE) — 공급사
        $this->addItem($yearMonth, $supplierId, SettleType::PURCHASE, $item, $qty, $purchaseUnit, $purchaseAmount);
    }

    private function addItem(string $yearMonth, int $orgId, SettleType $type, UsageReportItem $item, int $qty, float $unitPrice, float $amount): void
    {
        $settlement = Settlement::firstOrCreate(
            ['year_month' => $yearMonth, 'org_id' => $orgId, 'settle_type' => $type->value],
            ['status' => SettlementStatus::OPEN, 'total_qty' => 0, 'total_amount' => 0]
        );

        $settlement->items()->create([
            'usage_report_item_id' => $item->id,
            'product_id' => $item->product_id,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => $amount,
        ]);

        // 정산서 합계 갱신
        DB::table('settlements')->where('id', $settlement->id)->update([
            'total_qty' => DB::raw("total_qty + {$qty}"),
            'total_amount' => DB::raw('total_amount + '.number_format($amount, 2, '.', '')),
            'updated_at' => now(),
        ]);
    }
}
