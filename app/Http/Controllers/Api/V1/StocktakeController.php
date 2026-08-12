<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\StocktakeStatus;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\ProductLot;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Services\DocumentNoService;
use App\Services\StocktakeService;
use App\Support\Gs1Parser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 재고 실사 — 현재고 스냅샷 생성 → 실물 카운트 → 확정(차이만큼 ADJUST).
 *
 * 실사는 선반 앞에서 하는 일이라 모바일이 본진이다. 웹과 다른 점은 하나:
 * 웹은 표에서 수량을 타이핑하지만 앱은 **스캔할 때마다 1씩 올린다**(POST /{id}/scan).
 * 그래서 명세를 통째로 내려주지 않고 페이징 + 필터(미입력/차이)로 나눠 준다.
 *
 * 확정 로직은 웹과 같은 StocktakeService 에 위임한다. 재고를 건드리는 코드가
 * 두 벌이 되면 웹과 앱의 결과가 어긋난다.
 */
class StocktakeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Stocktake::query()
            ->with('organization:id,name')
            ->withCount([
                'items',
                'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty'),
                'items as diff_count' => fn ($q) => $q->whereNotNull('counted_qty')
                    ->whereColumn('counted_qty', '!=', 'system_qty'),
            ])
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('org_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('stocktake_no', 'like', "%{$v}%"))
            // 아직 확정되지 않은 것만 — 현장에서 이어서 셀 건을 찾는 용도.
            ->when($request->boolean('open'), fn ($q) => $q->where('status', '!=', StocktakeStatus::CONFIRMED->value))
            ->orderByDesc('id');

        $open = (clone $query)->reorder()
            ->where('status', '!=', StocktakeStatus::CONFIRMED->value)->count();

        $summary = $this->statusSummary($query, [
            StocktakeStatus::DRAFT->value => ['임시저장', 'hold'],
            StocktakeStatus::COUNTING->value => ['실사중', 'info'],
            StocktakeStatus::CONFIRMED->value => ['확정', 'ok'],
        ], [
            $this->stat('전체', (clone $query)->reorder()->count(), '건'),
            $this->stat('진행 중', $open, '건', $open > 0 ? 'warn' : 'ok'),
        ]);

        $query->reorder()->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'oldest' => $q->orderBy('id'),
            'date' => $q->orderByDesc('count_date')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (Stocktake $s) => $this->row($s), $summary);
    }

    /**
     * 실사를 만들 수 있는 대상 조직.
     *
     * 본사는 모든 창고·병원을, 나머지는 자기 조직만 실사한다. 웹 화면의 "대상 선택"과 같다.
     */
    public function targets(Request $request): JsonResponse
    {
        $user = $request->user();

        $orgs = $user->role === OrgType::HQ
            ? Organization::query()
                ->whereIn('org_type', [OrgType::WAREHOUSE, OrgType::HOSPITAL])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'org_type', 'name'])
            : Organization::query()->whereKey($user->org_id)->get(['id', 'org_type', 'name']);

        return response()->json([
            'data' => $orgs->map(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'org_type' => $o->org_type->value,
                'org_type_label' => $o->org_type->label(),
                // 지금 재고가 몇 건 잡히는지 — 스냅샷 규모를 미리 알려 준다.
                'lot_count' => (int) DB::table('stock_balances')
                    ->where('org_id', $o->id)->where('qty', '>', 0)->count(),
            ])->all(),
        ]);
    }

    /** 실사 생성 — 대상 조직의 현재고를 스냅샷으로 담는다. */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'org_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'count_date' => ['nullable', 'date'],
        ], [], ['org_id' => '실사 대상', 'count_date' => '실사일']);

        // 본사가 아니면 남의 조직을 실사할 수 없다(요청 값 위조 방지).
        $orgId = $user->role === OrgType::HQ ? (int) $validated['org_id'] : (int) $user->org_id;

        // 같은 조직에 진행 중인 실사가 있으면 새로 만들지 않는다. 스냅샷이 둘이면
        // 어느 쪽 차이를 반영해야 할지 알 수 없고, 확정 순서에 따라 재고가 어긋난다.
        $running = Stocktake::query()
            ->where('org_id', $orgId)
            ->where('status', '!=', StocktakeStatus::CONFIRMED->value)
            ->first();

        if ($running !== null) {
            return response()->json([
                'message' => "이미 진행 중인 실사가 있습니다 ({$running->stocktake_no}). 먼저 확정하거나 이어서 진행하세요.",
                'id' => $running->id,
            ], 422);
        }

        $stocktake = DB::transaction(function () use ($orgId, $validated, $docNo, $user) {
            $stocktake = Stocktake::create([
                'stocktake_no' => $docNo->next('ST'),
                'org_id' => $orgId,
                'status' => StocktakeStatus::COUNTING,
                'count_date' => $validated['count_date'] ?? now()->toDateString(),
                'created_by' => $user->id,
            ]);

            $balances = DB::table('stock_balances')
                ->where('org_id', $orgId)->where('qty', '>', 0)
                ->get(['product_id', 'lot_id', 'qty']);

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

        return response()->json([
            'message' => "실사 {$stocktake->stocktake_no} 을(를) 시작했습니다.",
            'id' => $stocktake->id,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $stocktake = $this->find($id);
        $stocktake->load('organization:id,name');

        return response()->json(['data' => $this->row($stocktake, $request) + [
            'confirmed_at' => $stocktake->confirmed_at?->toIso8601String(),
        ]]);
    }

    /**
     * 실사 명세 — 항목이 수백 건일 수 있어 페이징한다.
     *
     * filter: uncounted(미입력) | counted(입력) | diff(차이) | all
     */
    public function items(Request $request, int $id): JsonResponse
    {
        $stocktake = $this->find($id);

        $query = StocktakeItem::query()
            ->where('stocktake_id', $stocktake->id)
            ->with(['product:id,product_code,product_name,spec,unit', 'lot:id,lot_no,expiry_date'])
            // OR 조건은 반드시 묶는다 — 안 묶으면 stocktake_id 조건까지 OR 로 새어 나간다.
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->whereHas('product', fn ($p) => $p
                    ->where('product_name', 'like', "%{$kw}%")
                    ->orWhere('product_code', 'like', "%{$kw}%"))
                ->orWhereHas('lot', fn ($l) => $l->where('lot_no', 'like', "%{$kw}%"))));

        $query->when($request->string('filter')->toString(), fn ($q, $f) => match ($f) {
            'uncounted' => $q->whereNull('counted_qty'),
            'counted' => $q->whereNotNull('counted_qty'),
            'diff' => $q->whereNotNull('counted_qty')->whereColumn('counted_qty', '!=', 'system_qty'),
            default => $q,
        });

        $query->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            // 차이가 큰 것부터 — 확정 전에 눈으로 확인할 순서.
            'diff' => $q->orderByRaw('ABS(COALESCE(counted_qty, system_qty) - system_qty) DESC')->orderBy('id'),
            'recent' => $q->orderByDesc('updated_at')->orderBy('id'),
            default => $q->orderBy('id'),
        });

        $paginator = $query->paginate($this->pageSize($request, 30), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (StocktakeItem $it) => $this->item($it));
    }

    /**
     * 스캔 카운트 — 앱의 핵심 동작.
     *
     * 바코드에 Lot 이 들어 있으면 그 Lot 을 바로 집는다. 없으면 해당 제품의 Lot 이
     * 하나뿐일 때만 자동으로 집고, 여러 개면 후보를 돌려주어 사용자가 고르게 한다.
     * 스냅샷에 없던 Lot 이 나오면(장부에 없는 실물) system_qty 0 으로 새로 담는다 —
     * 실사의 목적이 바로 이 차이를 잡아내는 것이기 때문이다.
     */
    public function scan(Request $request, int $id, Gs1Parser $parser): JsonResponse
    {
        $stocktake = $this->editable($id);

        $validated = $request->validate([
            'scan' => ['required', 'string', 'max:200'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
            // 여러 Lot 중 사용자가 고른 경우.
            'lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
        ], [], ['scan' => '바코드']);

        $qty = (int) ($validated['qty'] ?? 1);
        $data = $parser->parse($validated['scan']);

        if (! $data->hasGtin()) {
            return response()->json([
                'message' => '바코드에서 제품 식별자(GTIN)를 찾지 못했습니다. 다시 스캔해 주세요.',
            ], 422);
        }

        $product = DB::table('products')->where('gtin', $data->gtin)->where('is_active', true)->first();

        if ($product === null) {
            return response()->json([
                'message' => "등록되지 않은 제품입니다(GTIN {$data->gtin}).",
            ], 422);
        }

        $lotId = $validated['lot_id'] ?? null;

        // 바코드에 Lot 번호가 있으면 그것으로 특정한다.
        if ($lotId === null && ($data->lotNo ?? '') !== '') {
            $lotId = ProductLot::query()
                ->where('product_id', $product->id)->where('lot_no', $data->lotNo)
                ->value('id');

            if ($lotId === null) {
                return response()->json([
                    'message' => "등록되지 않은 Lot 입니다({$data->lotNo}). 입고 이력을 확인하세요.",
                ], 422);
            }
        }

        // Lot 을 모르면 스냅샷 안에서 후보를 찾는다.
        if ($lotId === null) {
            $candidates = StocktakeItem::query()
                ->where('stocktake_id', $stocktake->id)
                ->where('product_id', $product->id)
                ->with('lot:id,lot_no,expiry_date')
                ->get();

            if ($candidates->isEmpty()) {
                return response()->json([
                    'message' => "{$product->product_name} 은(는) 이 실사 대상 재고에 없습니다. Lot 이 찍힌 바코드를 스캔해 주세요.",
                ], 422);
            }

            if ($candidates->count() > 1) {
                return response()->json([
                    'needs_lot' => true,
                    'message' => 'Lot 을 선택해 주세요.',
                    'product_name' => $product->product_name,
                    'candidates' => $candidates->map(fn (StocktakeItem $it) => [
                        'lot_id' => $it->lot_id,
                        'lot_no' => $it->lot?->lot_no,
                        'expiry_date' => $it->lot?->expiry_date?->toDateString(),
                        'system_qty' => $it->system_qty,
                        'counted_qty' => $it->counted_qty,
                    ])->all(),
                ], 409);
            }

            $lotId = $candidates->first()->lot_id;
        }

        $item = DB::transaction(function () use ($stocktake, $product, $lotId, $qty) {
            $item = StocktakeItem::query()
                ->where('stocktake_id', $stocktake->id)
                ->where('lot_id', $lotId)
                ->lockForUpdate()
                ->first();

            // 장부에 없던 실물 — 시스템 수량 0 으로 새로 담는다.
            $item ??= $stocktake->items()->create([
                'product_id' => $product->id,
                'lot_id' => $lotId,
                'system_qty' => 0,
                'counted_qty' => 0,
                'diff_qty' => 0,
            ]);

            $counted = (int) ($item->counted_qty ?? 0) + $qty;
            $item->update([
                'counted_qty' => $counted,
                'diff_qty' => $counted - (int) $item->system_qty,
            ]);

            return $item;
        });

        $item->load(['product:id,product_code,product_name,spec,unit', 'lot:id,lot_no,expiry_date']);

        return response()->json([
            'message' => "{$item->product?->product_name} · {$item->lot?->lot_no} → {$item->counted_qty}",
            'item' => $this->item($item),
            'progress' => $this->progress($stocktake),
        ]);
    }

    /** 수량 직접 입력·정정. counted_qty=null 이면 미입력으로 되돌린다. */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $stocktake = $this->editable($id);

        $validated = $request->validate([
            'counted_qty' => ['present', 'nullable', 'integer', 'min:0', 'max:999999'],
        ], [], ['counted_qty' => '실사 수량']);

        $item = StocktakeItem::query()
            ->where('stocktake_id', $stocktake->id)->find($itemId);

        abort_if($item === null, 404, '실사 명세를 찾을 수 없습니다.');

        $counted = $validated['counted_qty'];
        $item->update([
            'counted_qty' => $counted,
            'diff_qty' => $counted === null ? 0 : (int) $counted - (int) $item->system_qty,
        ]);

        $item->load(['product:id,product_code,product_name,spec,unit', 'lot:id,lot_no,expiry_date']);

        return response()->json([
            'message' => '저장했습니다.',
            'item' => $this->item($item),
            'progress' => $this->progress($stocktake),
        ]);
    }

    /**
     * 확정 — 차이만큼 ADJUST 트랜잭션이 생성된다(되돌릴 수 없다).
     *
     * 미입력 항목은 서비스가 "시스템 수량과 같다"로 처리하므로 차이가 0이다.
     */
    public function confirm(Request $request, int $id, StocktakeService $service): JsonResponse
    {
        $stocktake = $this->find($id);

        try {
            $service->confirm($stocktake, $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->ok("실사 {$stocktake->stocktake_no} 을(를) 확정했습니다. 차이만큼 재고가 조정되었습니다.");
    }

    // ────────────────────────────────────────────────────────────── 내부

    /**
     * OrgLocationScope 가 창고·병원을 자기 조직으로 이미 좁혀 준다.
     * 본사·라이프는 전체를 보되, 쓰기는 store() 에서 대상을 다시 검증한다.
     */
    private function find(int $id): Stocktake
    {
        $stocktake = Stocktake::query()->find($id);

        abort_if($stocktake === null, 404, '실사 문서를 찾을 수 없습니다.');

        return $stocktake;
    }

    /** 확정된 실사는 더 이상 손댈 수 없다. */
    private function editable(int $id): Stocktake
    {
        $stocktake = $this->find($id);

        abort_if(
            $stocktake->status === StocktakeStatus::CONFIRMED,
            422,
            '이미 확정된 실사입니다. 수정할 수 없습니다.',
        );

        return $stocktake;
    }

    /**
     * 진행률 — 앱 상단 바에 그대로 그린다.
     *
     * @return array<string, int>
     */
    private function progress(Stocktake $stocktake): array
    {
        $row = DB::table('stocktake_items')
            ->where('stocktake_id', $stocktake->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN counted_qty IS NOT NULL THEN 1 ELSE 0 END) as counted,
                SUM(CASE WHEN counted_qty IS NOT NULL AND counted_qty <> system_qty THEN 1 ELSE 0 END) as diff
            ')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'counted' => (int) ($row->counted ?? 0),
            'diff' => (int) ($row->diff ?? 0),
        ];
    }

    /**
     * 목록·상세 공통 행. $request 가 있으면 버튼까지 붙인다.
     *
     * @return array<string, mixed>
     */
    private function row(Stocktake $stocktake, ?Request $request = null): array
    {
        // 목록은 withCount 로 이미 세어 왔고, 상세는 직접 센다.
        $progress = $stocktake->items_count === null
            ? $this->progress($stocktake)
            : [
                'total' => (int) $stocktake->items_count,
                'counted' => (int) $stocktake->counted_count,
                'diff' => (int) $stocktake->diff_count,
            ];

        return [
            'id' => $stocktake->id,
            'stocktake_no' => $stocktake->stocktake_no,
            'org_id' => $stocktake->org_id,
            'org_name' => $stocktake->organization?->name,
            'status' => $stocktake->status->value,
            'status_label' => $stocktake->status->label(),
            'tone' => $this->tone($stocktake->status),
            'count_date' => $stocktake->count_date?->toDateString(),
            'progress' => $progress,
            'created_at' => $stocktake->created_at?->toIso8601String(),
            'actions' => $request === null ? [] : $this->actions($request, $stocktake, $progress),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(StocktakeItem $it): array
    {
        $counted = $it->counted_qty;

        return [
            'id' => $it->id,
            'product_id' => $it->product_id,
            'product_code' => $it->product?->product_code,
            'product_name' => $it->product?->product_name,
            'spec' => $it->product?->spec,
            'unit' => $it->product?->unit,
            'lot_id' => $it->lot_id,
            'lot_no' => $it->lot?->lot_no,
            'expiry_date' => $it->lot?->expiry_date?->toDateString(),
            'system_qty' => (int) $it->system_qty,
            'counted_qty' => $counted === null ? null : (int) $counted,
            'diff_qty' => $counted === null ? 0 : (int) $counted - (int) $it->system_qty,
            // 색은 서버가 정한다 — 미입력/일치/차이의 의미가 화면마다 흔들리면 안 된다.
            'tone' => match (true) {
                $counted === null => 'hold',
                (int) $counted === (int) $it->system_qty => 'ok',
                default => 'crit',
            },
        ];
    }

    /**
     * 이 사용자가 지금 할 수 있는 동작.
     *
     * @param  array<string, int>  $progress
     * @return array<int, array{key: string, label: string, tone: string}>
     */
    private function actions(Request $request, Stocktake $stocktake, array $progress): array
    {
        if ($stocktake->status === StocktakeStatus::CONFIRMED) {
            return [];
        }

        $user = $request->user();
        $mine = $user->role === OrgType::HQ || $stocktake->org_id === $user->org_id;

        if (! $mine) {
            return [];
        }

        return [[
            'key' => 'confirm',
            'label' => '실사 확정',
            // 차이가 있으면 재고가 실제로 움직인다 — 경고 톤으로 알린다.
            'tone' => $progress['diff'] > 0 ? 'crit' : 'ok',
        ]];
    }

    private function tone(StocktakeStatus $status): string
    {
        return match ($status) {
            StocktakeStatus::DRAFT => 'hold',
            StocktakeStatus::COUNTING => 'info',
            StocktakeStatus::CONFIRMED => 'ok',
        };
    }
}
