<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Enums\Severity;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Outbound;
use Illuminate\Support\Facades\DB;

/**
 * 자동 보충 (CLAUDE.md §7.2). 병원 재고가 안전재고 미만이면:
 *  - 창고 재고 충분 → Outbound(DRAFT, AUTO_REPLENISH) 자동 생성 + 창고 알림
 *  - 창고 재고 부족 → 공급사 부족 알림(발주 요청) + 본사 알림
 * 트리거: 사용분 승인 후 / 매일 새벽 Scheduler(replenishment:check).
 */
class ReplenishmentService
{
    public function __construct(private readonly DocumentNoService $docNo) {}

    /**
     * 특정 병원(또는 전체)의 안전재고 미달 품목을 점검하고 보충을 제안한다.
     *
     * @return array{created: int, shortages: int} 생성된 자동 출고 수 / 공급사 부족 알림 수
     */
    public function check(?int $hospitalId = null): array
    {
        $warehouse = Organization::where('org_type', OrgType::WAREHOUSE)->where('is_active', true)->first();

        // 안전재고 미달 (병원 × 품목)
        $rows = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->join('organizations as h', 'h.id', '=', 's.hospital_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->when($hospitalId, fn ($q) => $q->where('s.hospital_id', $hospitalId))
            ->groupBy('s.hospital_id', 'h.name', 's.product_id', 'p.product_name', 'p.supplier_id', 's.safety_qty', 's.reorder_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->select(
                's.hospital_id', 'h.name as hospital_name', 's.product_id', 'p.product_name', 'p.supplier_id',
                's.safety_qty', 's.reorder_qty', DB::raw('COALESCE(SUM(b.qty),0) as onhand')
            )->get();

        $created = 0;
        $shortages = 0;

        foreach ($rows as $row) {
            $need = max((int) $row->reorder_qty, (int) $row->safety_qty - (int) $row->onhand);

            // 중복 방지: 미완료 자동보충이 이미 있으면 스킵
            $existing = Outbound::query()
                ->where('hospital_id', $row->hospital_id)
                ->where('source_type', OutboundSourceType::AUTO_REPLENISH)
                ->whereIn('status', [OutboundStatus::DRAFT->value, OutboundStatus::APPROVED->value, OutboundStatus::PICKING->value, OutboundStatus::SHIPPED->value])
                ->whereHas('items', fn ($q) => $q->where('product_id', $row->product_id))
                ->exists();
            if ($existing) {
                continue;
            }

            $whStock = $warehouse
                ? (int) DB::table('stock_balances')->where('org_id', $warehouse->id)->where('product_id', $row->product_id)->sum('qty')
                : 0;

            if ($warehouse && $whStock >= $need) {
                // 창고 충분 → 자동 출고(DRAFT) 생성 + 창고 알림
                DB::transaction(function () use ($warehouse, $row, $need) {
                    $outbound = Outbound::create([
                        'outbound_no' => $this->docNo->next('OB'),
                        'warehouse_id' => $warehouse->id,
                        'hospital_id' => $row->hospital_id,
                        'status' => OutboundStatus::DRAFT,
                        'source_type' => OutboundSourceType::AUTO_REPLENISH,
                        'planned_date' => now()->toDateString(),
                    ]);
                    $outbound->items()->create(['product_id' => $row->product_id, 'qty' => $need]);
                });

                Notification::create([
                    'noti_type' => NotiType::SAFETY_STOCK,
                    'severity' => Severity::WARNING,
                    'target_org_id' => $warehouse->id,
                    'title' => '자동 보충 출고 생성',
                    'message' => "{$row->hospital_name} · {$row->product_name} 안전재고 미달 → 자동 출고(초안) {$need} 생성",
                    'link_url' => '/outbounds',
                    'is_read' => false,
                ]);
                $created++;
            } else {
                // 창고 부족 → 공급사 부족 알림(발주 요청)
                Notification::create([
                    'noti_type' => NotiType::SAFETY_STOCK,
                    'severity' => Severity::CRITICAL,
                    'target_org_id' => $row->supplier_id,
                    'title' => '납품 요청(재고 부족)',
                    'message' => "{$row->product_name} 창고 재고 부족 — {$row->hospital_name} 보충용 {$need} 납품 요청",
                    'link_url' => '/supplier/shortages',
                    'is_read' => false,
                ]);
                $shortages++;
            }

            // 병원 알림
            Notification::create([
                'noti_type' => NotiType::SAFETY_STOCK,
                'severity' => Severity::WARNING,
                'target_org_id' => $row->hospital_id,
                'title' => '안전재고 미달',
                'message' => "{$row->product_name} 현재고 {$row->onhand} / 안전 {$row->safety_qty} — 보충이 진행됩니다.",
                'link_url' => '/inventory/status',
                'is_read' => false,
            ]);
        }

        return ['created' => $created, 'shortages' => $shortages];
    }
}
