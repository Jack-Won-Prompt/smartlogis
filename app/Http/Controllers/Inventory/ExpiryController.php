<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 유통기한 임박 — D-30/60/90 필터. 재고가 있는(qty>0) Lot 중 임박분을 위치 스코프로 조회한다.
 */
class ExpiryController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $within = in_array($request->integer('within', 90), [30, 60, 90], true) ? $request->integer('within', 90) : 90;
        $limitDate = $today->copy()->addDays($within)->toDateString();

        $query = StockBalance::query()
            ->with(['organization', 'product', 'lot'])
            ->where('qty', '>', 0)
            ->whereHas('lot', fn ($q) => $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $limitDate))
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->whereHas('product', fn ($s) => $s
                ->where('product_name', 'like', "%{$kw}%")->orWhere('product_code', 'like', "%{$kw}%")));

        // 유통기한 임박 순
        $query->join('product_lots as pl', 'pl.id', '=', 'stock_balances.lot_id')
            ->orderBy('pl.expiry_date')
            ->select('stock_balances.*');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(function (StockBalance $b) use ($today) {
                $days = (int) $today->diffInDays($b->lot->expiry_date, false);

                return [
                    'id' => "{$b->org_id}:{$b->product_id}:{$b->lot_id}",
                    'org_name' => $b->organization->name,
                    'product_code' => $b->product->product_code,
                    'product_name' => $b->product->product_name,
                    'lot_no' => $b->lot->lot_no,
                    'expiry_date' => $b->lot->expiry_date?->toDateString(),
                    'expiry_days' => $days,
                    'qty' => $b->qty,
                ];
            })->all(),
        ]);
    }

    public function index(): View
    {
        return view('inventory.expiry');
    }
}
