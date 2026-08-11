<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\OrgType;
use App\Enums\StocktakeStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Services\DocumentNoService;
use App\Services\StocktakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 재고 실사 — 현재고 스냅샷 생성 → 실사 수량 입력 → 확정(diff 만큼 ADJUST).
 */
class StocktakeController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $query = Stocktake::query()->with('organization')->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (Stocktake $s) => [
                'id' => $s->id,
                'stocktake_no' => $s->stocktake_no,
                'org_id' => $s->org_id,
                'org_name' => $s->organization->name,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'count_date' => $s->count_date?->toDateString(),
                'items_count' => $s->items_count,
            ])->all(),
        ]);
    }

    /** 실사 생성 — 대상 조직의 현재고를 스냅샷으로 담는다. */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $validated = $request->validate([
            'org_id' => ['required', 'integer', 'exists:organizations,id'],
            'count_date' => ['required', 'date'],
        ], [], ['org_id' => '실사 대상', 'count_date' => '실사일']);

        $stocktake = DB::transaction(function () use ($validated, $docNo, $request) {
            $stocktake = Stocktake::create([
                'stocktake_no' => $docNo->next('ST'),
                'org_id' => $validated['org_id'],
                'status' => StocktakeStatus::COUNTING,
                'count_date' => $validated['count_date'],
                'created_by' => $request->user()?->id,
            ]);

            $balances = DB::table('stock_balances')->where('org_id', $validated['org_id'])->where('qty', '>', 0)->get();
            foreach ($balances as $b) {
                $stocktake->items()->create([
                    'product_id' => $b->product_id,
                    'lot_id' => $b->lot_id,
                    'system_qty' => $b->qty,
                    'counted_qty' => null,
                    'diff_qty' => 0,
                ]);
            }

            return $stocktake;
        });

        return response()->json(['id' => $stocktake->id, 'stocktake_no' => $stocktake->stocktake_no]);
    }

    public function show(Stocktake $stocktake): JsonResponse
    {
        $stocktake->load(['organization', 'items.product', 'items.lot']);

        return response()->json([
            'id' => $stocktake->id,
            'stocktake_no' => $stocktake->stocktake_no,
            'status' => $stocktake->status->value,
            'status_label' => $stocktake->status->label(),
            'org_name' => $stocktake->organization->name,
            'items' => $stocktake->items->map(fn (StocktakeItem $it) => [
                'id' => $it->id,
                'product_code' => $it->product->product_code,
                'product_name' => $it->product->product_name,
                'lot_no' => $it->lot->lot_no,
                'system_qty' => $it->system_qty,
                'counted_qty' => $it->counted_qty,
            ])->all(),
        ]);
    }

    /** 실사 수량 입력. */
    public function updateItem(Request $request, Stocktake $stocktake, StocktakeItem $item): JsonResponse
    {
        abort_if($item->stocktake_id !== $stocktake->id, 404);
        $validated = $request->validate(['counted_qty' => ['required', 'integer', 'min:0']]);
        $item->update(['counted_qty' => $validated['counted_qty']]);

        return response()->json(['diff' => (int) $validated['counted_qty'] - (int) $item->system_qty]);
    }

    public function confirm(Stocktake $stocktake, StocktakeService $service, Request $request): JsonResponse
    {
        $service->confirm($stocktake, $request->user()?->id);

        return response()->json(['message' => "{$stocktake->stocktake_no} 확정 완료 — 차이만큼 재고가 조정되었습니다."]);
    }

    public function index(): View
    {
        $me = auth()->user();
        $orgs = $me->isHq()
            ? Organization::whereIn('org_type', [OrgType::WAREHOUSE, OrgType::HOSPITAL])->orderBy('name')->get(['id', 'name'])
            : Organization::whereKey($me->org_id)->get(['id', 'name']);

        return view('stocktake.index', ['orgs' => $orgs]);
    }
}
