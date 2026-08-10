<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrgType;
use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 역할별 대시보드. 현재 시드에 존재하는 재고/마스터 데이터를 기반으로 실제 지표를 계산한다.
 * 사용분·정산 등 트랜잭션 이력이 쌓이는 Phase 5 이후 매출 추이 카드가 채워진다.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $me = auth()->user();
        $role = $me->role;

        return view('dashboard', [
            'role' => $role,
            'flow' => $this->flowToday(),
            'kpis' => $this->kpis($role, $me->org_id),
            'charts' => $this->charts($role, $me->org_id),
        ]);
    }

    /**
     * 오늘 각 구간 이동 수량(미니 Flow Rail용).
     *
     * @return array<string, int>
     */
    private function flowToday(): array
    {
        $rows = DB::table('stock_transactions')
            ->select('tx_type', DB::raw('SUM(ABS(qty)) as total'))
            ->whereDate('created_at', Carbon::today())
            ->groupBy('tx_type')
            ->pluck('total', 'tx_type');

        return [
            'SUPPLIER' => (int) ($rows['IN_SUPPLIER'] ?? 0),
            'WAREHOUSE' => (int) ($rows['OUT_TO_HOSPITAL'] ?? 0),
            'HOSPITAL' => (int) ($rows['IN_HOSPITAL'] ?? 0),
            'USAGE' => (int) ($rows['USE'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpis(OrgType $role, int $orgId): array
    {
        $today = Carbon::today();

        // 유통기한 임박(D-30) Lot 수 — 역할별 위치 필터
        $expiryQuery = ProductLot::query()->expiringWithin(30);
        $expirySoon = (clone $expiryQuery)->count();

        // 안전재고 미달 건수
        $belowSafety = $this->belowSafetyCount($role, $orgId);

        // 총 현재고 수량(역할 위치 스코프) — DB sum 은 문자열을 반환하므로 정수 캐스팅
        $stockQty = (int) $this->scopedBalance($role, $orgId)->sum('b.qty');

        $activeProducts = Product::query()->where('is_active', true)->count();

        return match ($role) {
            OrgType::HQ => [
                ['label' => '승인 대기 사용분', 'value' => number_format($this->usagePending()), 'tone' => 'warn', 'suffix' => '건'],
                ['label' => '안전재고 미달', 'value' => number_format($belowSafety), 'tone' => 'crit', 'suffix' => '건'],
                ['label' => 'D-30 유통기한 임박', 'value' => number_format($expirySoon), 'tone' => 'crit', 'suffix' => 'Lot'],
                ['label' => '활성 제품', 'value' => number_format($activeProducts), 'tone' => null, 'suffix' => '종'],
            ],
            OrgType::WAREHOUSE => [
                ['label' => '창고 총 재고', 'value' => number_format($stockQty), 'tone' => null, 'suffix' => 'EA'],
                ['label' => '오늘 입고', 'value' => number_format($this->flowToday()['SUPPLIER']), 'tone' => 'info', 'suffix' => 'EA'],
                ['label' => 'D-30 유통기한 임박', 'value' => number_format($expirySoon), 'tone' => 'crit', 'suffix' => 'Lot'],
                ['label' => '취급 제품', 'value' => number_format($activeProducts), 'tone' => null, 'suffix' => '종'],
            ],
            OrgType::HOSPITAL => [
                ['label' => '병원 총 재고', 'value' => number_format($stockQty), 'tone' => null, 'suffix' => 'EA'],
                ['label' => '안전재고 미달', 'value' => number_format($belowSafety), 'tone' => 'crit', 'suffix' => '건'],
                ['label' => 'D-30 유통기한 임박', 'value' => number_format($expirySoon), 'tone' => 'warn', 'suffix' => 'Lot'],
                ['label' => '취급 품목', 'value' => number_format($this->hospitalProductCount($orgId)), 'tone' => null, 'suffix' => '종'],
            ],
            OrgType::SUPPLIER => [
                ['label' => '자사 제품', 'value' => number_format($activeProducts), 'tone' => null, 'suffix' => '종'],
                ['label' => '병원 재고 합계', 'value' => number_format($stockQty), 'tone' => null, 'suffix' => 'EA'],
                ['label' => '안전재고 미달 병원', 'value' => number_format($belowSafety), 'tone' => 'warn', 'suffix' => '건'],
                ['label' => 'D-30 유통기한 임박', 'value' => number_format($expirySoon), 'tone' => 'crit', 'suffix' => 'Lot'],
            ],
            OrgType::LIFE => [
                ['label' => '승인 대기 요청', 'value' => number_format($this->usagePending()), 'tone' => 'warn', 'suffix' => '건'],
                ['label' => '안전재고 미달', 'value' => number_format($belowSafety), 'tone' => 'crit', 'suffix' => '건'],
                ['label' => 'D-30 유통기한 임박', 'value' => number_format($expirySoon), 'tone' => 'warn', 'suffix' => 'Lot'],
                ['label' => '활성 제품', 'value' => number_format($activeProducts), 'tone' => null, 'suffix' => '종'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function charts(OrgType $role, int $orgId): array
    {
        // 유통기한 구간 분포
        $today = Carbon::today();
        $expiryBuckets = [
            'D-30 이내/경과' => ProductLot::whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $today->copy()->addDays(30))->count(),
            'D-60' => ProductLot::whereNotNull('expiry_date')->whereBetween('expiry_date', [$today->copy()->addDays(31), $today->copy()->addDays(60)])->count(),
            'D-90' => ProductLot::whereNotNull('expiry_date')->whereBetween('expiry_date', [$today->copy()->addDays(61), $today->copy()->addDays(90)])->count(),
            '여유(90+)' => ProductLot::whereNotNull('expiry_date')->whereDate('expiry_date', '>', $today->copy()->addDays(90))->count(),
        ];

        // 보관유형별 제품 수
        $byStorage = Product::query()
            ->select('storage_type', DB::raw('count(*) as c'))
            ->groupBy('storage_type')->pluck('c', 'storage_type');
        $storageLabels = ['ROOM' => '실온', 'COLD' => '냉장', 'FROZEN' => '냉동'];
        $storage = [];
        foreach ($storageLabels as $k => $lbl) {
            $storage[$lbl] = (int) ($byStorage[$k] ?? 0);
        }

        // 거점(조직)별 재고 분포 — 상위 6
        $byOrg = $this->scopedBalance($role, $orgId)
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->select('o.name', DB::raw('SUM(b.qty) as total'))
            ->groupBy('o.id', 'o.name')
            ->orderByDesc('total')
            ->limit(6)->get();

        // 월별 매출/매입 추이 (역할별)
        $trend = $this->trend($role, $orgId);

        return [
            'expiry' => ['labels' => array_keys($expiryBuckets), 'data' => array_values($expiryBuckets)],
            'storage' => ['labels' => array_keys($storage), 'data' => array_values($storage)],
            'byOrg' => ['labels' => $byOrg->pluck('name')->all(), 'data' => $byOrg->pluck('total')->map(fn ($v) => (int) $v)->all()],
            'trend' => $trend,
        ];
    }

    /**
     * 월별 정산 추이. HQ=매출, 병원=자사 매출, 공급사=자사 매입.
     *
     * @return array{labels: array<int, string>, data: array<int, float>, label: string}
     */
    private function trend(OrgType $role, int $orgId): array
    {
        $q = DB::table('settlements')->select('year_month', DB::raw('SUM(total_amount) as amt'))
            ->groupBy('year_month')->orderBy('year_month');

        $label = '월별 매출';
        if ($role === OrgType::SUPPLIER) {
            $q->where('settle_type', 'PURCHASE')->where('org_id', $orgId);
            $label = '월별 매입';
        } elseif ($role === OrgType::HOSPITAL) {
            $q->where('settle_type', 'SALES')->where('org_id', $orgId);
        } else {
            $q->where('settle_type', 'SALES');
        }

        $rows = $q->get();

        return [
            'labels' => $rows->pluck('year_month')->all(),
            'data' => $rows->pluck('amt')->map(fn ($v) => (float) $v)->all(),
            'label' => $label,
        ];
    }

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

        $grouped = $q->select('s.hospital_id', 's.product_id', 's.safety_qty')
            ->groupBy('s.hospital_id', 's.product_id', 's.safety_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty');

        // 그룹 결과의 행 수를 DB 레벨에서 센다.
        return (int) DB::query()->fromSub($grouped, 'below')->count();
    }

    private function usagePending(): int
    {
        return DB::table('usage_reports')->where('status', 'SUBMITTED')->count();
    }

    private function hospitalProductCount(int $orgId): int
    {
        return (int) DB::table('safety_stocks')->where('hospital_id', $orgId)->distinct('product_id')->count('product_id');
    }
}
