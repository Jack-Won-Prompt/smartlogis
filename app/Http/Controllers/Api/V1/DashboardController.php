<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\OutboundStatus;
use App\Enums\UsageStatus;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 모바일 역할별 대시보드. 웹 DashboardController 와 같은 지표를 쓰되
 * 카드 3~4개 + 차트 2개로 축약하고, 화면에서 바로 그릴 수 있는 형태로 내려준다.
 */
class DashboardController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;
        $orgId = $user->org_id;

        return response()->json([
            'role' => $role->value,
            'role_label' => $role->label(),
            'org_name' => $user->organization?->name,
            'generated_at' => now()->toIso8601String(),
            'flow' => $this->flowToday(),
            'kpis' => $this->kpis($role, $orgId),
            'trend' => $this->trend($role, $orgId),
            'expiry_buckets' => $this->expiryBuckets($role, $orgId),
            'shortcuts' => $this->shortcuts($role),
        ]);
    }

    /**
     * 오늘 물류 흐름(공급사 입고 → 창고 출고 → 병원 입고 → 사용).
     *
     * @return array<int, array{key: string, label: string, qty: int}>
     */
    private function flowToday(): array
    {
        $rows = DB::table('stock_transactions')
            ->select('tx_type', DB::raw('SUM(ABS(qty)) as total'))
            ->whereDate('created_at', Carbon::today())
            ->groupBy('tx_type')
            ->pluck('total', 'tx_type');

        return [
            ['key' => 'SUPPLIER', 'label' => '공급사 입고', 'qty' => (int) ($rows['IN_SUPPLIER'] ?? 0)],
            ['key' => 'WAREHOUSE', 'label' => '창고 출고', 'qty' => (int) ($rows['OUT_TO_HOSPITAL'] ?? 0)],
            ['key' => 'HOSPITAL', 'label' => '병원 입고', 'qty' => (int) ($rows['IN_HOSPITAL'] ?? 0)],
            ['key' => 'USAGE', 'label' => '사용', 'qty' => (int) ($rows['USE'] ?? 0)],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, suffix: string, tone: string, route: string|null}>
     */
    private function kpis(OrgType $role, int $orgId): array
    {
        $expirySoon = $this->expiringLotCount($role, $orgId, 30);
        $belowSafety = $this->belowSafetyCount($role, $orgId);
        $stockQty = (int) $this->scopedBalance($role, $orgId)->sum('b.qty');

        return match ($role) {
            OrgType::HQ => [
                $this->kpi('승인 대기 사용분', $this->usagePendingCount(), '건', 'warn', '/usages?status=SUBMITTED'),
                $this->kpi('안전재고 미달', $belowSafety, '건', 'crit', '/inventory/shortages'),
                $this->kpi('D-30 유통기한', $expirySoon, 'Lot', 'crit', '/inventory/expiry'),
                $this->kpi('배송 진행', $this->outboundInTransitCount(), '건', 'info', '/outbounds'),
            ],
            OrgType::WAREHOUSE => [
                $this->kpi('창고 재고', $stockQty, 'EA', 'ok', '/inventory/stocks'),
                $this->kpi('검수 대기 입고', $this->inboundPendingCount($orgId), '건', 'warn', '/inbounds'),
                $this->kpi('출고 대기', $this->outboundToPickCount($orgId), '건', 'info', '/outbounds'),
                $this->kpi('D-30 유통기한', $expirySoon, 'Lot', 'crit', '/inventory/expiry'),
            ],
            OrgType::HOSPITAL => [
                $this->kpi('병원 재고', $stockQty, 'EA', 'ok', '/inventory/stocks'),
                $this->kpi('안전재고 미달', $belowSafety, '건', 'crit', '/inventory/shortages'),
                $this->kpi('미전송 사용분', $this->usageDraftCount($orgId), '건', 'warn', '/usages?status=DRAFT'),
                $this->kpi('D-30 유통기한', $expirySoon, 'Lot', 'warn', '/inventory/expiry'),
            ],
            OrgType::SUPPLIER => [
                $this->kpi('자사 제품', Product::query()->where('is_active', true)->count(), '종', 'ok', '/supplier/stocks'),
                $this->kpi('병원 재고 합계', $stockQty, 'EA', 'ok', '/supplier/stocks'),
                $this->kpi('부족 발생', $belowSafety, '건', 'crit', '/supplier/shortages'),
                $this->kpi('진행 중 납품', $this->inboundOpenBySupplierCount($orgId), '건', 'info', '/inbounds'),
            ],
            OrgType::LIFE => [
                $this->kpi('승인 대기 요청', $this->usagePendingCount(), '건', 'warn', '/usages?status=SUBMITTED'),
                $this->kpi('가용 재고 조회', $stockQty, 'EA', 'ok', '/inventory/stocks'),
                $this->kpi('안전재고 미달', $belowSafety, '건', 'crit', '/inventory/shortages'),
                $this->kpi('D-30 유통기한', $expirySoon, 'Lot', 'warn', '/inventory/expiry'),
            ],
        };
    }

    /**
     * @return array{label: string, value: int, suffix: string, tone: string, route: string|null}
     */
    private function kpi(string $label, int $value, string $suffix, string $tone, ?string $route): array
    {
        return compact('label', 'value', 'suffix', 'tone', 'route');
    }

    /**
     * 최근 6개월 정산 추이.
     *
     * @return array{label: string, points: array<int, array{month: string, amount: float}>}
     */
    private function trend(OrgType $role, int $orgId): array
    {
        $since = Carbon::today()->subMonths(5)->format('Y-m');

        $q = DB::table('settlements')
            ->select('year_month', DB::raw('SUM(total_amount) as amt'))
            ->where('year_month', '>=', $since)
            ->groupBy('year_month')
            ->orderBy('year_month');

        $label = '월별 매출';

        if ($role === OrgType::SUPPLIER) {
            $q->where('settle_type', 'PURCHASE')->where('org_id', $orgId);
            $label = '월별 매입';
        } elseif ($role === OrgType::HOSPITAL) {
            $q->where('settle_type', 'SALES')->where('org_id', $orgId);
        } else {
            $q->where('settle_type', 'SALES');
        }

        return [
            'label' => $label,
            'points' => $q->get()->map(fn ($r) => [
                'month' => (string) $r->year_month,
                'amount' => (float) $r->amt,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, tone: string}>
     */
    private function expiryBuckets(OrgType $role, int $orgId): array
    {
        return [
            ['label' => 'D-30 이내', 'count' => $this->expiringLotCount($role, $orgId, 30), 'tone' => 'crit'],
            ['label' => 'D-60', 'count' => $this->expiringLotCount($role, $orgId, 60) - $this->expiringLotCount($role, $orgId, 30), 'tone' => 'warn'],
            ['label' => 'D-90', 'count' => $this->expiringLotCount($role, $orgId, 90) - $this->expiringLotCount($role, $orgId, 60), 'tone' => 'info'],
        ];
    }

    /**
     * 역할별 빠른 실행(모바일 홈 상단 액션 칩).
     *
     * @return array<int, array{key: string, label: string, icon: string, route: string}>
     */
    private function shortcuts(OrgType $role): array
    {
        return match ($role) {
            OrgType::HQ => [
                ['key' => 'approval', 'label' => '사용분 승인', 'icon' => 'approval', 'route' => '/usages?status=SUBMITTED'],
                ['key' => 'scan', 'label' => '바코드 조회', 'icon' => 'scan', 'route' => '/scan'],
                ['key' => 'settlement', 'label' => '정산', 'icon' => 'settlement', 'route' => '/settlements'],
                ['key' => 'expiry', 'label' => '유통기한', 'icon' => 'expiry', 'route' => '/inventory/expiry'],
            ],
            OrgType::WAREHOUSE => [
                ['key' => 'receiving', 'label' => '스캔 입고', 'icon' => 'scan_in', 'route' => '/inbounds?mode=receiving'],
                ['key' => 'picking', 'label' => '피킹/출고', 'icon' => 'picking', 'route' => '/outbounds'],
                ['key' => 'stock', 'label' => '재고 조회', 'icon' => 'stock', 'route' => '/inventory/stocks'],
                ['key' => 'expiry', 'label' => '유통기한', 'icon' => 'expiry', 'route' => '/inventory/expiry'],
            ],
            OrgType::HOSPITAL => [
                ['key' => 'usage', 'label' => '사용분 스캔', 'icon' => 'scan_use', 'route' => '/usages/create'],
                ['key' => 'stock', 'label' => '재고 조회', 'icon' => 'stock', 'route' => '/inventory/stocks'],
                ['key' => 'inbound', 'label' => '입고 확인', 'icon' => 'inbound', 'route' => '/inbounds'],
                ['key' => 'expiry', 'label' => '유통기한', 'icon' => 'expiry', 'route' => '/inventory/expiry'],
            ],
            OrgType::LIFE => [
                ['key' => 'request', 'label' => '물품 요청', 'icon' => 'asn', 'route' => '/usages/create'],
                ['key' => 'use', 'label' => '사용 확정', 'icon' => 'scan_use', 'route' => '/usages'],
                ['key' => 'stock', 'label' => '재고 조회', 'icon' => 'stock', 'route' => '/inventory/stocks'],
                ['key' => 'return', 'label' => '반납', 'icon' => 'inbound', 'route' => '/returns'],
            ],
            OrgType::SUPPLIER => [
                ['key' => 'asn', 'label' => '납품 등록', 'icon' => 'asn', 'route' => '/inbounds/create'],
                ['key' => 'shortage', 'label' => '부족 품목', 'icon' => 'shortage', 'route' => '/supplier/shortages'],
                ['key' => 'stocks', 'label' => '병원 재고', 'icon' => 'stock', 'route' => '/supplier/stocks'],
                ['key' => 'scan', 'label' => '바코드 조회', 'icon' => 'scan', 'route' => '/scan'],
            ],
        };
    }

    // ---------------------------------------------------------------- 집계 헬퍼

    /** 역할 위치로 스코프된 stock_balances 쿼리(별칭 b). */
    private function scopedBalance(OrgType $role, int $orgId): Builder
    {
        $q = DB::table('stock_balances as b');

        if ($role === OrgType::WAREHOUSE || $role === OrgType::HOSPITAL) {
            $q->where('b.org_id', $orgId);
        } elseif ($role === OrgType::SUPPLIER) {
            $q->whereIn('b.product_id', DB::table('products')->select('id')->where('supplier_id', $orgId));
        }

        return $q;
    }

    /** 재고가 실제로 있는 위치 기준 유통기한 임박 Lot 수. */
    private function expiringLotCount(OrgType $role, int $orgId, int $days): int
    {
        return (int) $this->scopedBalance($role, $orgId)
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->where('b.qty', '>', 0)
            ->whereNotNull('l.expiry_date')
            ->whereDate('l.expiry_date', '<=', Carbon::today()->addDays($days))
            ->distinct()
            ->count('b.lot_id');
    }

    private function belowSafetyCount(OrgType $role, int $orgId): int
    {
        $q = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            });

        if ($role === OrgType::HOSPITAL) {
            $q->where('s.hospital_id', $orgId);
        } elseif ($role === OrgType::SUPPLIER) {
            $q->whereIn('s.product_id', DB::table('products')->select('id')->where('supplier_id', $orgId));
        }

        $rows = $q->groupBy('s.hospital_id', 's.product_id', 's.safety_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->select('s.hospital_id')
            ->get();

        return $rows->count();
    }

    private function usagePendingCount(): int
    {
        return (int) DB::table('usage_reports')->where('status', UsageStatus::SUBMITTED->value)->count();
    }

    private function usageDraftCount(int $orgId): int
    {
        return (int) DB::table('usage_reports')
            ->where('hospital_id', $orgId)
            ->whereIn('status', [UsageStatus::DRAFT->value, UsageStatus::REJECTED->value])
            ->count();
    }

    private function outboundInTransitCount(): int
    {
        return (int) DB::table('outbounds')
            ->whereIn('status', [OutboundStatus::PICKING->value, OutboundStatus::SHIPPED->value])
            ->count();
    }

    private function outboundToPickCount(int $orgId): int
    {
        return (int) DB::table('outbounds')
            ->where('warehouse_id', $orgId)
            ->whereIn('status', [OutboundStatus::DRAFT->value, OutboundStatus::APPROVED->value])
            ->count();
    }

    private function inboundPendingCount(int $orgId): int
    {
        return (int) DB::table('inbounds')
            ->where('to_org_id', $orgId)
            ->whereIn('status', ['PLANNED', 'RECEIVING'])
            ->count();
    }

    private function inboundOpenBySupplierCount(int $orgId): int
    {
        return (int) DB::table('inbounds')
            ->where('from_org_id', $orgId)
            ->whereIn('status', ['PLANNED', 'RECEIVING'])
            ->count();
    }
}
