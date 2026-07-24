<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Lot 추적 (리콜 대응). 제품/Lot 을 선택하면 해당 Lot 의 모든 재고 이동 이력을 시간순으로 보여준다.
 */
class LotTraceController extends Controller
{
    /** Lot 검색(제품명/코드/Lot 번호). */
    public function lots(Request $request): JsonResponse
    {
        $kw = $request->string('keyword')->toString();

        $lots = ProductLot::query()->with('product')
            ->when($kw, fn ($q) => $q->where('lot_no', 'like', "%{$kw}%")
                ->orWhereHas('product', fn ($s) => $s->where('product_name', 'like', "%{$kw}%")->orWhere('product_code', 'like', "%{$kw}%")))
            ->orderByDesc('id')->limit(30)->get();

        return response()->json($lots->map(fn (ProductLot $l) => [
            'id' => $l->id,
            'label' => "{$l->product->product_name} · Lot {$l->lot_no}".($l->expiry_date ? " (EXP {$l->expiry_date->toDateString()})" : ''),
        ]));
    }

    /** 선택 Lot 의 이동 이력. */
    public function trace(Request $request, ProductLot $lot): JsonResponse
    {
        $txs = StockTransaction::query()
            ->with(['organization', 'creator'])
            ->where('lot_id', $lot->id)
            ->orderBy('created_at')->orderBy('id')
            ->get();

        return response()->json([
            'lot' => [
                'lot_no' => $lot->lot_no,
                'product_name' => $lot->product->product_name,
                'expiry_date' => $lot->expiry_date?->toDateString(),
            ],
            'data' => $txs->map(fn (StockTransaction $t) => [
                'id' => $t->id,
                'at' => Carbon::parse($t->created_at)->timezone('Asia/Seoul')->format('Y-m-d H:i'),
                'tx_type' => $t->tx_type->label(),
                'org_name' => $t->organization->name,
                'qty' => $t->qty,
                'ref' => $t->ref_type?->label(),
                'memo' => $t->memo,
            ])->all(),
        ]);
    }

    public function index(): View
    {
        return view('inventory.lot-trace');
    }
}
