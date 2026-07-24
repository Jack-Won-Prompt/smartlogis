<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supplier;

use App\Enums\OrgType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 공급사 화면 — 자사 제품의 병원별 재고 / 안전재고 부족(납품 요청).
 * 공급사는 SupplierProductScope 원칙에 따라 자사 제품 관련 데이터만 본다.
 */
class SupplierController extends Controller
{
    /** 대상 공급사 id — SUPPLIER 는 자기, HQ 는 쿼리 파라미터(선택). */
    private function supplierId(Request $request): ?int
    {
        $user = $request->user();

        return $user->role === OrgType::SUPPLIER ? $user->org_id : ($request->integer('supplier_id') ?: null);
    }

    /** 자사 제품의 병원별 현재고. */
    public function stocksData(Request $request): JsonResponse
    {
        $supplierId = $this->supplierId($request);

        $query = DB::table('stock_balances as b')
            ->join('organizations as o', 'o.id', '=', 'b.org_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->where('o.org_type', OrgType::HOSPITAL->value)
            ->where('b.qty', '>', 0)
            ->when($supplierId, fn ($q) => $q->where('p.supplier_id', $supplierId))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('p.product_name', 'like', "%{$kw}%")->orWhere('o.name', 'like', "%{$kw}%")))
            ->orderBy('p.product_name')->orderBy('o.name')
            ->select('o.name as hospital_name', 'p.product_code', 'p.product_name', 'l.lot_no', 'l.expiry_date', 'b.qty');

        $size = min(max($request->integer('size', 10), 1), 100);
        $total = (clone $query)->count();
        $rows = $query->forPage($request->integer('page', 1), $size)->get();

        return response()->json([
            'last_page' => (int) ceil($total / $size),
            'total' => $total,
            'data' => $rows->map(fn ($r) => [
                'id' => uniqid(),
                'hospital_name' => $r->hospital_name,
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'lot_no' => $r->lot_no,
                'expiry_date' => $r->expiry_date,
                'qty' => (int) $r->qty,
            ])->all(),
        ]);
    }

    /** 자사 제품 중 안전재고 미달 병원(납품 요청 대상). */
    public function shortagesData(Request $request): JsonResponse
    {
        $supplierId = $this->supplierId($request);

        $rows = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->join('organizations as h', 'h.id', '=', 's.hospital_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->when($supplierId, fn ($q) => $q->where('p.supplier_id', $supplierId))
            ->groupBy('h.name', 'p.product_code', 'p.product_name', 's.safety_qty', 's.reorder_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->select('h.name as hospital_name', 'p.product_code', 'p.product_name', 's.safety_qty', 's.reorder_qty', DB::raw('COALESCE(SUM(b.qty),0) as onhand'))
            ->orderBy('p.product_name')->get();

        return response()->json([
            'last_page' => 1,
            'total' => $rows->count(),
            'data' => $rows->map(fn ($r) => [
                'id' => uniqid(),
                'hospital_name' => $r->hospital_name,
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'onhand' => (int) $r->onhand,
                'safety_qty' => (int) $r->safety_qty,
                'shortage' => (int) $r->safety_qty - (int) $r->onhand,
                'reorder_qty' => (int) $r->reorder_qty,
            ])->all(),
        ]);
    }

    public function stocks(): View
    {
        return view('supplier.stocks');
    }

    public function shortages(): View
    {
        return view('supplier.shortages');
    }
}
