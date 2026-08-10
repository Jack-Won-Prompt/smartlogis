<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\OrgType;
use App\Enums\OutboundStatus;
use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 재고 현황 — 위치×제품×Lot 현재고. 역할별 위치 스코프는 OrgLocationScope/SupplierProductScope 가 강제한다.
 */
class StockStatusController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $query = StockBalance::query()
            ->with(['organization', 'product', 'lot'])
            ->where('qty', '>', 0)
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->whereHas('product', fn ($s) => $s
                ->where('product_name', 'like', "%{$kw}%")->orWhere('product_code', 'like', "%{$kw}%")))
            ->orderBy('org_id')->orderBy('product_id')->orderBy('lot_id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        // 예약(승인·피킹 중 미출고) 수량을 (창고, 제품) 단위로 한 번에 집계 → 가용재고 계산.
        $orgProductKeys = $p->getCollection()
            ->map(fn (StockBalance $b) => [$b->org_id, $b->product_id])->unique()->values();
        $reservedMap = [];
        if ($orgProductKeys->isNotEmpty()) {
            $rows = DB::table('outbound_items as oi')
                ->join('outbounds as o', 'o.id', '=', 'oi.outbound_id')
                ->whereIn('o.status', [OutboundStatus::APPROVED->value, OutboundStatus::PICKING->value])
                ->whereIn('o.warehouse_id', $orgProductKeys->pluck(0)->unique()->all())
                ->whereIn('oi.product_id', $orgProductKeys->pluck(1)->unique()->all())
                ->groupBy('o.warehouse_id', 'oi.product_id')
                ->get(['o.warehouse_id', 'oi.product_id', DB::raw('SUM(oi.qty) as reserved')]);
            foreach ($rows as $r) {
                $reservedMap["{$r->warehouse_id}:{$r->product_id}"] = (int) $r->reserved;
            }
        }
        // (창고, 제품) 총 현재고 — 가용 = 총재고 − 예약.
        $totalMap = [];
        foreach ($orgProductKeys as [$oid, $pid]) {
            $totalMap["{$oid}:{$pid}"] = (int) StockBalance::query()
                ->where('org_id', $oid)->where('product_id', $pid)->sum('qty');
        }

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(function (StockBalance $b) use ($today, $reservedMap, $totalMap) {
                $opKey = "{$b->org_id}:{$b->product_id}";
                $reserved = $reservedMap[$opKey] ?? 0;
                $available = max(0, ($totalMap[$opKey] ?? 0) - $reserved);
                $days = $b->lot?->expiry_date
                    ? (int) Carbon::parse($today)->diffInDays($b->lot->expiry_date, false)
                    : null;
                $safety = $b->organization->org_type === OrgType::HOSPITAL
                    ? (int) DB::table('safety_stocks')->where('hospital_id', $b->org_id)->where('product_id', $b->product_id)->value('safety_qty')
                    : 0;

                return [
                    'id' => "{$b->org_id}:{$b->product_id}:{$b->lot_id}",
                    'org_name' => $b->organization->name,
                    'product_code' => $b->product->product_code,
                    'product_name' => $b->product->product_name,
                    'lot_no' => $b->lot->lot_no,
                    'expiry_date' => $b->lot->expiry_date?->toDateString(),
                    'expiry_days' => $days,
                    'qty' => $b->qty,
                    'unit' => (float) $b->product->sales_price,   // 사용분 등록 화면 금액 표시용
                    'reserved_qty' => $reserved,   // 품목 단위 예약(승인·피킹 중)
                    'available_qty' => $available, // 품목 단위 가용재고(총−예약)
                    'safety_qty' => $safety,
                ];
            })->all(),
        ]);
    }

    public function index(): View
    {
        return view('inventory.status');
    }
}
