<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 재고 현황 / 유통기한 임박 / 안전재고 미달 / Lot 추적.
 *
 * 데이터 스코프는 웹과 동일한 규칙을 쿼리빌더에 직접 적용한다
 * (stock_balances 는 복합 PK 라 Eloquent 대신 쿼리빌더 집계를 쓰는 편이 정확하다).
 */
class InventoryController extends ApiController
{
    /** 위치 × 제품 단위로 묶은 재고 현황. Lot 상세는 lots() 로 따로 조회한다. */
    public function stocks(Request $request): JsonResponse
    {
        $page = max($request->integer('page', 1), 1);
        $size = $this->pageSize($request);

        $base = $this->scoped($request)
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->leftJoin('safety_stocks as s', function ($j) {
                $j->on('s.hospital_id', '=', 'b.org_id')->on('s.product_id', '=', 'b.product_id');
            })
            ->where('b.qty', '>', 0)
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('b.org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('p.product_name', 'like', "%{$kw}%")
                ->orWhere('p.product_code', 'like', "%{$kw}%")
                ->orWhere('p.gtin', 'like', "%{$kw}%")))
            ->groupBy('b.org_id', 'o.name', 'b.product_id', 'p.product_code', 'p.product_name', 'p.spec', 'p.unit', 's.safety_qty')
            ->select(
                'b.org_id', 'o.name as org_name', 'b.product_id',
                'p.product_code', 'p.product_name', 'p.spec', 'p.unit',
                DB::raw('SUM(b.qty) as qty'),
                DB::raw('COUNT(DISTINCT b.lot_id) as lot_count'),
                DB::raw('MAX(s.safety_qty) as safety_qty'),
            );

        if ($request->boolean('below_safety')) {
            $base->havingRaw('SUM(b.qty) < MAX(s.safety_qty)');
        }

        $total = DB::query()->fromSub($base, 't')->count();

        // 요약은 **전체 조건 기준**으로 낸다. 앱은 한 페이지(20건)만 갖고 있어
        // 스스로 합계를 낼 수 없다.
        $agg = DB::query()->fromSub($base, 't')
            ->selectRaw('
                COALESCE(SUM(t.qty), 0) as total_qty,
                SUM(CASE WHEN t.safety_qty IS NOT NULL AND t.qty < t.safety_qty THEN 1 ELSE 0 END) as crit_cnt,
                SUM(CASE WHEN t.safety_qty IS NOT NULL AND t.qty >= t.safety_qty
                          AND t.qty < t.safety_qty * 1.2 THEN 1 ELSE 0 END) as warn_cnt,
                SUM(CASE WHEN t.safety_qty IS NULL OR t.qty >= t.safety_qty * 1.2 THEN 1 ELSE 0 END) as ok_cnt
            ')
            ->first();

        $summary = [
            'stats' => [
                $this->stat('품목', $total, '건'),
                $this->stat('총 수량', number_format((int) ($agg->total_qty ?? 0)), 'EA'),
                $this->stat('안전재고 미달', (int) ($agg->crit_cnt ?? 0), '건',
                    ((int) ($agg->crit_cnt ?? 0)) > 0 ? 'crit' : 'ok'),
            ],
            'segments' => [
                $this->segment('충분', (int) ($agg->ok_cnt ?? 0), 'ok'),
                $this->segment('임박', (int) ($agg->warn_cnt ?? 0), 'warn'),
                $this->segment('미달', (int) ($agg->crit_cnt ?? 0), 'crit'),
            ],
        ];

        $rows = (clone $base)
            ->orderByRaw($this->stockSort($request))
            ->forPage($page, $size)
            ->get();

        return $this->pagedRaw($rows->map(fn ($r) => [
            'org_id' => (int) $r->org_id,
            'org_name' => $r->org_name,
            'product_id' => (int) $r->product_id,
            'product_code' => $r->product_code,
            'product_name' => $r->product_name,
            'spec' => $r->spec,
            'unit' => $r->unit,
            'qty' => (int) $r->qty,
            'lot_count' => (int) $r->lot_count,
            'safety_qty' => $r->safety_qty !== null ? (int) $r->safety_qty : null,
            'level' => $this->level((int) $r->qty, $r->safety_qty !== null ? (int) $r->safety_qty : null),
        ])->all(), $total, $page, $size, $summary);
    }

    /**
     * 재고 목록 정렬. 화이트리스트로만 받는다(사용자 입력을 SQL 에 그대로 넣지 않는다).
     *
     * 기본은 미달 필터가 켜져 있으면 부족한 것부터, 아니면 품목명순이다.
     */
    private function stockSort(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'qty_desc' => 'SUM(b.qty) desc',
            'qty_asc' => 'SUM(b.qty) asc',
            'name' => 'p.product_name asc',
            // 안전재고 대비 충족률이 낮은 순 — "가장 급한 것" 이 위로.
            'shortage' => 'CASE WHEN MAX(s.safety_qty) > 0
                                THEN SUM(b.qty) / MAX(s.safety_qty) ELSE 999 END asc',
            default => $request->boolean('below_safety')
                ? 'SUM(b.qty) asc'
                : 'p.product_name asc',
        };
    }

    /** 특정 위치·제품의 Lot 별 재고 (FEFO 순). */
    public function lots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'org_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $rows = $this->scoped($request)
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->where('b.product_id', $validated['product_id'])
            ->where('b.qty', '>', 0)
            ->when($validated['org_id'] ?? null, fn ($q, $v) => $q->where('b.org_id', $v))
            ->orderByRaw('CASE WHEN l.expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('l.expiry_date')
            ->select('l.id', 'l.lot_no', 'l.expiry_date', 'b.qty', 'b.org_id', 'o.name as org_name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'lot_no' => $r->lot_no,
                'expiry_date' => $r->expiry_date,
                'days_to_expiry' => $this->daysTo($r->expiry_date),
                'qty' => (int) $r->qty,
                'org_id' => (int) $r->org_id,
                'org_name' => $r->org_name,
            ])->all(),
        ]);
    }

    /** 유통기한 임박(D-30/60/90, 경과 포함). */
    public function expiry(Request $request): JsonResponse
    {
        $page = max($request->integer('page', 1), 1);
        $size = $this->pageSize($request);
        $days = in_array($request->integer('days', 30), [30, 60, 90, 180], true)
            ? $request->integer('days', 30)
            : 30;

        $base = $this->scoped($request)
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->where('b.qty', '>', 0)
            ->whereNotNull('l.expiry_date')
            ->whereDate('l.expiry_date', '<=', Carbon::today()->addDays($days))
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('b.org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('p.product_name', 'like', "%{$kw}%")
                ->orWhere('l.lot_no', 'like', "%{$kw}%")))
            ->select(
                'l.id as lot_id', 'l.lot_no', 'l.expiry_date', 'b.qty',
                'p.product_code', 'p.product_name', 'p.unit', 'o.name as org_name',
            );

        $total = DB::query()->fromSub(clone $base, 't')->count();

        // 임박 구간별 Lot 수 — "얼마나 급한지" 를 목록 스크롤 없이 알 수 있어야 한다.
        $today = Carbon::today();
        $agg = DB::query()->fromSub(clone $base, 't')
            ->selectRaw('
                COALESCE(SUM(t.qty), 0) as total_qty,
                SUM(CASE WHEN t.expiry_date < ? THEN 1 ELSE 0 END) as expired_cnt,
                SUM(CASE WHEN t.expiry_date >= ? AND t.expiry_date <= ? THEN 1 ELSE 0 END) as d30_cnt,
                SUM(CASE WHEN t.expiry_date > ? AND t.expiry_date <= ? THEN 1 ELSE 0 END) as d60_cnt,
                SUM(CASE WHEN t.expiry_date > ? THEN 1 ELSE 0 END) as rest_cnt
            ', [
                $today->toDateString(),
                $today->toDateString(), $today->copy()->addDays(30)->toDateString(),
                $today->copy()->addDays(30)->toDateString(), $today->copy()->addDays(60)->toDateString(),
                $today->copy()->addDays(60)->toDateString(),
            ])
            ->first();

        $expired = (int) ($agg->expired_cnt ?? 0);
        $summary = [
            'stats' => [
                $this->stat('대상 Lot', $total, '건'),
                $this->stat('총 수량', number_format((int) ($agg->total_qty ?? 0)), 'EA'),
                $this->stat('기한 경과', $expired, '건', $expired > 0 ? 'crit' : 'ok'),
            ],
            'segments' => [
                $this->segment('경과', $expired, 'crit'),
                $this->segment('D-30', (int) ($agg->d30_cnt ?? 0), 'warn'),
                $this->segment('D-60', (int) ($agg->d60_cnt ?? 0), 'info'),
                $this->segment('그 외', (int) ($agg->rest_cnt ?? 0), 'ok'),
            ],
        ];

        $rows = $base
            ->orderByRaw(match ($request->string('sort')->toString()) {
                'qty_desc' => 'b.qty desc',
                'product' => 'p.product_name asc',
                default => 'l.expiry_date asc',   // 임박순이 기본 — 가장 급한 것부터
            })
            ->forPage($page, $size)
            ->get();

        return $this->pagedRaw($rows->map(fn ($r) => [
            'lot_id' => (int) $r->lot_id,
            'lot_no' => $r->lot_no,
            'expiry_date' => $r->expiry_date,
            'days_to_expiry' => $this->daysTo($r->expiry_date),
            'qty' => (int) $r->qty,
            'unit' => $r->unit,
            'product_code' => $r->product_code,
            'product_name' => $r->product_name,
            'org_name' => $r->org_name,
        ])->all(), $total, $page, $size, $summary);
    }

    /** 안전재고 미달 목록(병원 × 품목). */
    public function shortages(Request $request): JsonResponse
    {
        $user = $request->user();

        $q = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->join('organizations as h', 'h.id', '=', 's.hospital_id')
            ->join('products as p', 'p.id', '=', 's.product_id');

        if ($user->role === OrgType::HOSPITAL) {
            $q->where('s.hospital_id', $user->org_id);
        } elseif ($user->role === OrgType::SUPPLIER) {
            $q->where('p.supplier_id', $user->org_id);
        }

        $rows = $q
            ->when($request->string('keyword')->toString(), fn ($qq, $kw) => $qq->where(fn ($s) => $s
                ->where('p.product_name', 'like', "%{$kw}%")->orWhere('h.name', 'like', "%{$kw}%")))
            ->groupBy('s.hospital_id', 'h.name', 's.product_id', 'p.product_code', 'p.product_name', 'p.unit', 's.safety_qty', 's.reorder_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->select(
                's.hospital_id', 'h.name as hospital_name', 's.product_id',
                'p.product_code', 'p.product_name', 'p.unit',
                's.safety_qty', 's.reorder_qty',
                DB::raw('COALESCE(SUM(b.qty),0) as onhand'),
            )
            ->orderByRaw(match ($request->string('sort')->toString()) {
                // 부족량이 큰 순 — 보충 발주를 어디부터 낼지 정하는 화면이다.
                'shortage' => '(s.safety_qty - COALESCE(SUM(b.qty),0)) desc',
                'hospital' => 'h.name asc',
                default => 'p.product_name asc',
            })
            ->get();

        // 이 화면은 페이징하지 않는다(미달 품목은 많아야 수백 건).
        // 그래도 "얼마나 부족한지" 총량은 한눈에 보여야 발주 판단이 선다.
        $shortageTotal = $rows->sum(fn ($r) => (int) $r->safety_qty - (int) $r->onhand);
        $reorderTotal = $rows->sum(fn ($r) => (int) $r->reorder_qty);
        $zeroCnt = $rows->filter(fn ($r) => (int) $r->onhand === 0)->count();
        $hospitalCnt = $rows->pluck('hospital_id')->unique()->count();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'hospital_id' => (int) $r->hospital_id,
                'hospital_name' => $r->hospital_name,
                'product_id' => (int) $r->product_id,
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'unit' => $r->unit,
                'onhand' => (int) $r->onhand,
                'safety_qty' => (int) $r->safety_qty,
                'shortage' => (int) $r->safety_qty - (int) $r->onhand,
                'reorder_qty' => (int) $r->reorder_qty,
            ])->all(),
            'summary' => [
                'stats' => [
                    $this->stat('미달 품목', $rows->count(), '건',
                        $rows->count() > 0 ? 'crit' : 'ok'),
                    $this->stat('부족 수량', number_format($shortageTotal), 'EA', 'crit'),
                    $this->stat('권장 발주', number_format($reorderTotal), 'EA', 'info'),
                ],
                'segments' => [
                    // 재고가 아예 0 인 건 발주가 아니라 즉시 대응이 필요하다.
                    $this->segment('재고 없음', $zeroCnt, 'crit'),
                    $this->segment('부족', $rows->count() - $zeroCnt, 'warn'),
                ],
            ],
            'meta' => ['total' => $rows->count(), 'hospitals' => $hospitalCnt],
        ]);
    }

    /** Lot 추적 — 해당 Lot 의 전 이동 이력(리콜 대응). */
    public function trace(Request $request, int $lotId): JsonResponse
    {
        $lot = DB::table('product_lots as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.id', $lotId)
            ->when($request->user()->role === OrgType::SUPPLIER,
                fn ($q) => $q->where('p.supplier_id', $request->user()->org_id))
            ->select('l.id', 'l.lot_no', 'l.expiry_date', 'p.product_code', 'p.product_name', 'p.unit')
            ->first();

        if ($lot === null) {
            return response()->json(['message' => '해당 Lot 을 찾을 수 없습니다.'], 404);
        }

        $txs = DB::table('stock_transactions as t')
            ->join('organizations as o', 'o.id', '=', 't.org_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.lot_id', $lotId)
            ->orderBy('t.created_at')
            ->select('t.id', 't.tx_type', 't.qty', 't.created_at', 't.memo', 'o.name as org_name', 'u.name as user_name')
            ->get();

        $balances = DB::table('stock_balances as b')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->where('b.lot_id', $lotId)
            ->where('b.qty', '>', 0)
            ->select('o.name as org_name', 'b.qty')
            ->get();

        return response()->json([
            'lot' => [
                'id' => (int) $lot->id,
                'lot_no' => $lot->lot_no,
                'expiry_date' => $lot->expiry_date,
                'days_to_expiry' => $this->daysTo($lot->expiry_date),
                'product_code' => $lot->product_code,
                'product_name' => $lot->product_name,
                'unit' => $lot->unit,
            ],
            'balances' => $balances->map(fn ($r) => [
                'org_name' => $r->org_name,
                'qty' => (int) $r->qty,
            ])->all(),
            'transactions' => $txs->map(fn ($r) => [
                'id' => (int) $r->id,
                'tx_type' => $r->tx_type,
                'tx_label' => \App\Enums\TxType::from($r->tx_type)->label(),
                'qty' => (int) $r->qty,
                'org_name' => $r->org_name,
                'user_name' => $r->user_name,
                'memo' => $r->memo,
                'created_at' => $r->created_at,
            ])->all(),
        ]);
    }

    // ---------------------------------------------------------------- 헬퍼

    /** 역할별 데이터 스코프가 적용된 stock_balances 쿼리(별칭 b). */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        $q = DB::table('stock_balances as b');

        if ($user->role === OrgType::WAREHOUSE || $user->role === OrgType::HOSPITAL) {
            $q->where('b.org_id', $user->org_id);
        } elseif ($user->role === OrgType::SUPPLIER) {
            $q->whereIn('b.product_id', DB::table('products')->select('id')->where('supplier_id', $user->org_id));
        }

        return $q;
    }

    private function daysTo(?string $date): ?int
    {
        if ($date === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false);
    }

    /** 재고 수준 신호등 — DESIGN.md 시맨틱 색과 1:1 대응. */
    private function level(int $qty, ?int $safety): string
    {
        if ($safety === null || $safety <= 0) {
            return 'ok';
        }
        if ($qty < $safety) {
            return 'crit';
        }
        if ($qty < (int) ceil($safety * 1.2)) {
            return 'warn';
        }

        return 'ok';
    }
}
