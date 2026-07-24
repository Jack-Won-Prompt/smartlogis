<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\OrgType;
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

        // 병원 안전재고(대비 게이지) 조회 대상: 각 행의 hospital+product
        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(function (StockBalance $b) use ($today) {
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
