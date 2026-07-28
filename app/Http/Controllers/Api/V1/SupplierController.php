<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 공급사 전용 — 자사 제품의 병원별 재고 / 부족 발생 병원(납품 대상).
 * HQ 는 supplier_id 파라미터로 특정 공급사를 대신 조회할 수 있다.
 */
class SupplierController extends ApiController
{
    /** 자사 제품의 병원별 현재고 — 제품 기준으로 묶고 병원 분포를 함께 준다. */
    public function stocks(Request $request): JsonResponse
    {
        $supplierId = $this->supplierId($request);
        $page = max($request->integer('page', 1), 1);
        $size = $this->pageSize($request);

        $base = DB::table('stock_balances as b')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('o.org_type', OrgType::HOSPITAL->value)
            ->where('b.qty', '>', 0)
            ->when($supplierId, fn ($q, $v) => $q->where('p.supplier_id', $v))
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('b.org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('p.product_name', 'like', "%{$kw}%")
                ->orWhere('p.product_code', 'like', "%{$kw}%")
                ->orWhere('o.name', 'like', "%{$kw}%")))
            ->groupBy('p.id', 'p.product_code', 'p.product_name', 'p.unit')
            ->select(
                'p.id as product_id', 'p.product_code', 'p.product_name', 'p.unit',
                DB::raw('SUM(b.qty) as qty'),
                DB::raw('COUNT(DISTINCT b.org_id) as hospital_count'),
            );

        $total = DB::query()->fromSub(clone $base, 't')->count();

        // 공급사가 알고 싶은 것은 "내 물건이 병원에 얼마나 깔려 있나" 다.
        // 품목 수만으로는 알 수 없어 총 수량과 깔린 병원 수를 함께 낸다.
        $agg = DB::query()->fromSub(clone $base, 't')
            ->selectRaw('COALESCE(SUM(t.qty), 0) as total_qty, COALESCE(MAX(t.hospital_count), 0) as max_hosp')
            ->first();

        $summary = [
            'stats' => [
                $this->stat('자사 품목', $total, '종'),
                $this->stat('병원 재고', number_format((int) ($agg->total_qty ?? 0)), 'EA', 'ok'),
                $this->stat('최다 배치', (int) ($agg->max_hosp ?? 0), '개 병원', 'info'),
            ],
        ];

        $rows = $base
            ->orderByRaw(match ($request->string('sort')->toString()) {
                'qty_asc' => 'SUM(b.qty) asc',
                'hospitals' => 'COUNT(DISTINCT b.org_id) desc',
                'name' => 'p.product_name asc',
                default => 'SUM(b.qty) desc',   // 많이 깔린 것부터
            })
            ->forPage($page, $size)
            ->get();

        return $this->pagedRaw($rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'product_code' => $r->product_code,
            'product_name' => $r->product_name,
            'unit' => $r->unit,
            'qty' => (int) $r->qty,
            'hospital_count' => (int) $r->hospital_count,
        ])->all(), $total, $page, $size, $summary);
    }

    /** 특정 제품의 병원별 분포(상세 시트). */
    public function stockByHospital(Request $request, int $productId): JsonResponse
    {
        $supplierId = $this->supplierId($request);

        $rows = DB::table('stock_balances as b')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('safety_stocks as s', function ($j) {
                $j->on('s.hospital_id', '=', 'b.org_id')->on('s.product_id', '=', 'b.product_id');
            })
            ->where('o.org_type', OrgType::HOSPITAL->value)
            ->where('b.product_id', $productId)
            ->where('b.qty', '>', 0)
            ->when($supplierId, fn ($q, $v) => $q->where('p.supplier_id', $v))
            ->groupBy('o.id', 'o.name')
            ->select('o.id', 'o.name', DB::raw('SUM(b.qty) as qty'), DB::raw('MAX(s.safety_qty) as safety_qty'))
            ->orderByDesc(DB::raw('SUM(b.qty)'))
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'hospital_id' => (int) $r->id,
                'hospital_name' => $r->name,
                'qty' => (int) $r->qty,
                'safety_qty' => $r->safety_qty !== null ? (int) $r->safety_qty : null,
            ])->all(),
        ]);
    }

    /** 자사 제품 중 안전재고 미달 병원 — 납품 요청 대상. */
    public function shortages(Request $request): JsonResponse
    {
        $supplierId = $this->supplierId($request);

        $rows = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->join('organizations as h', 'h.id', '=', 's.hospital_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->when($supplierId, fn ($q, $v) => $q->where('p.supplier_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($x) => $x
                ->where('p.product_name', 'like', "%{$kw}%")->orWhere('h.name', 'like', "%{$kw}%")))
            ->groupBy('h.id', 'h.name', 'p.id', 'p.product_code', 'p.product_name', 'p.unit', 's.safety_qty', 's.reorder_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->select(
                'h.id as hospital_id', 'h.name as hospital_name',
                'p.id as product_id', 'p.product_code', 'p.product_name', 'p.unit',
                's.safety_qty', 's.reorder_qty',
                DB::raw('COALESCE(SUM(b.qty),0) as onhand'),
            )
            ->orderByRaw('(s.safety_qty - COALESCE(SUM(b.qty),0)) desc')
            ->get();

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
            'meta' => ['total' => $rows->count()],
        ]);
    }

    /** SUPPLIER 는 자기 조직, HQ 는 선택 파라미터. */
    private function supplierId(Request $request): ?int
    {
        $user = $request->user();

        return $user->role === OrgType::SUPPLIER
            ? $user->org_id
            : ($request->integer('supplier_id') ?: null);
    }
}
