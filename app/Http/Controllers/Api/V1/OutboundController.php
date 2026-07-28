<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Models\Outbound;
use App\Models\Product;
use App\Services\DocumentNoService;
use App\Services\InboundService;
use App\Services\OutboundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 출고 — 지시 생성 → FEFO 피킹 → 배송 → 배송완료(병원 입고 자동 생성).
 * 상태 전이는 전부 OutboundService 에 위임한다(재고 무결성).
 *
 * HospitalScope 가 병원 계정을 자기 병원 건으로 자동 제한한다.
 */
class OutboundController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)
            ->with(['warehouse:id,name', 'hospital:id,name'])
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('source_type')->toString(), fn ($q, $v) => $q->where('source_type', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('outbound_no', 'like', "%{$v}%"))
            ->when($request->boolean('open'), fn ($q) => $q->whereNotIn('status', [
                OutboundStatus::DELIVERED->value, OutboundStatus::CANCELED->value,
            ]))
            ->orderByDesc('id');

        // 출고는 파이프라인이라 "지금 어디에 얼마나 걸려 있는지" 가 중요하다.
        $open = (clone $query)->reorder()->whereNotIn('status', [
            OutboundStatus::DELIVERED->value, OutboundStatus::CANCELED->value,
        ])->count();

        $summary = $this->statusSummary($query, [
            OutboundStatus::DRAFT->value => ['작성', 'hold'],
            OutboundStatus::APPROVED->value => ['승인', 'info'],
            OutboundStatus::PICKING->value => ['피킹', 'warn'],
            OutboundStatus::SHIPPED->value => ['배송중', 'warn'],
            OutboundStatus::DELIVERED->value => ['완료', 'ok'],
        ], [
            $this->stat('전체', (clone $query)->reorder()->count(), '건'),
            $this->stat('진행 중', $open, '건', $open > 0 ? 'warn' : 'ok'),
        ]);

        $query->reorder()->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'oldest' => $q->orderBy('id'),
            'planned' => $q->orderBy('planned_date')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (Outbound $o) => [
            'id' => $o->id,
            'outbound_no' => $o->outbound_no,
            'warehouse_name' => $o->warehouse?->name,
            'hospital_name' => $o->hospital?->name,
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'tone' => $o->status->tone()->value,
            'source_type' => $o->source_type->value,
            'source_label' => $o->source_type->label(),
            'planned_date' => $o->planned_date?->toDateString(),
            'shipped_at' => $o->shipped_at?->toIso8601String(),
            'delivered_at' => $o->delivered_at?->toIso8601String(),
            'items_count' => $o->items_count,
        ], $summary);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $outbound = $this->find($request, $id);
        $outbound->load([
            'warehouse:id,name', 'hospital:id,name',
            'items.product:id,product_code,product_name,spec,unit',
            'items.lot:id,lot_no,expiry_date',
        ]);

        $isWarehouseSide = $request->user()->role === OrgType::HQ
            || $outbound->warehouse_id === $request->user()->org_id;

        return response()->json(['data' => [
            'id' => $outbound->id,
            'outbound_no' => $outbound->outbound_no,
            'warehouse_name' => $outbound->warehouse?->name,
            'hospital_name' => $outbound->hospital?->name,
            'status' => $outbound->status->value,
            'status_label' => $outbound->status->label(),
            'tone' => $outbound->status->tone()->value,
            'source_type' => $outbound->source_type->value,
            'source_label' => $outbound->source_type->label(),
            'planned_date' => $outbound->planned_date?->toDateString(),
            'shipped_at' => $outbound->shipped_at?->toIso8601String(),
            'delivered_at' => $outbound->delivered_at?->toIso8601String(),
            'memo' => $outbound->memo,
            'actions' => $this->actions($outbound, $isWarehouseSide),
            'items' => $outbound->items->map(fn ($it) => [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'product_code' => $it->product?->product_code,
                'product_name' => $it->product?->product_name,
                'spec' => $it->product?->spec,
                'unit' => $it->product?->unit,
                'lot_id' => $it->lot_id,
                'lot_no' => $it->lot?->lot_no,
                'expiry_date' => $it->lot?->expiry_date?->toDateString(),
                'qty' => $it->qty,
                'allocated' => $it->lot_id !== null,
            ])->all(),
        ]]);
    }

    /** 수동 출고 지시 생성 (창고/본사). */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'hospital_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'HOSPITAL')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'WAREHOUSE')],
            'planned_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ], [], ['hospital_id' => '병원', 'items' => '출고 명세']);

        $warehouseId = $user->role === OrgType::WAREHOUSE
            ? $user->org_id
            : ($validated['warehouse_id'] ?? null);

        if ($warehouseId === null) {
            return response()->json(['message' => '출고 창고를 선택해 주세요.'], 422);
        }

        $outbound = DB::transaction(function () use ($validated, $warehouseId, $docNo, $user) {
            $outbound = Outbound::create([
                'outbound_no' => $docNo->next('OB'),
                'warehouse_id' => $warehouseId,
                'hospital_id' => $validated['hospital_id'],
                'status' => OutboundStatus::APPROVED,
                'source_type' => OutboundSourceType::MANUAL,
                'planned_date' => $validated['planned_date'] ?? now()->toDateString(),
                'memo' => $validated['memo'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $outbound->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => (float) ($product?->sales_price ?? 0),
                ]);
            }

            return $outbound;
        });

        return response()->json([
            'message' => "출고 지시 {$outbound->outbound_no} 이(가) 생성되었습니다.",
            'id' => $outbound->id,
            'outbound_no' => $outbound->outbound_no,
        ], 201);
    }

    /** FEFO 피킹 — 유통기한 임박 Lot 부터 자동 배정하고 창고 재고를 차감한다. */
    public function pick(Request $request, int $id, OutboundService $service): JsonResponse
    {
        $outbound = $this->findForWarehouse($request, $id);

        $service->pick($outbound, $request->user()->id);

        return $this->ok("{$outbound->outbound_no} 피킹 완료 — FEFO 로 Lot 이 배정되었습니다.");
    }

    public function ship(Request $request, int $id, OutboundService $service): JsonResponse
    {
        $outbound = $this->findForWarehouse($request, $id);

        $service->ship($outbound);

        return $this->ok("{$outbound->outbound_no} 배송을 시작했습니다.");
    }

    /** 배송 완료 → 병원 입고 문서 자동 생성·확정(병원 재고 증가). */
    public function deliver(Request $request, int $id, OutboundService $service, InboundService $inboundService): JsonResponse
    {
        $outbound = $this->find($request, $id);

        // 배송 완료는 받는 병원 또는 보낸 창고/본사 누구나 확인할 수 있다.
        $user = $request->user();
        $allowed = $user->role === OrgType::HQ
            || $outbound->warehouse_id === $user->org_id
            || $outbound->hospital_id === $user->org_id;

        abort_unless($allowed, 403, '이 출고를 처리할 권한이 없습니다.');

        $service->deliver($outbound, $inboundService, $user->id);

        return $this->ok("{$outbound->outbound_no} 배송 완료 — 병원 재고에 반영되었습니다.");
    }

    // ---------------------------------------------------------------- 스코프

    /** @return Builder<Outbound> */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        $query = Outbound::query(); // HospitalScope 가 병원 계정을 자동 필터

        if ($user->role === OrgType::WAREHOUSE) {
            $query->where('warehouse_id', $user->org_id);
        }

        return $query;
    }

    private function find(Request $request, int $id): Outbound
    {
        $outbound = $this->scoped($request)->find($id);

        abort_if($outbound === null, 404, '출고 문서를 찾을 수 없습니다.');

        return $outbound;
    }

    private function findForWarehouse(Request $request, int $id): Outbound
    {
        $outbound = $this->find($request, $id);
        $user = $request->user();

        abort_unless(
            $user->role === OrgType::HQ || $outbound->warehouse_id === $user->org_id,
            403,
            '출고 창고 담당자만 처리할 수 있습니다.',
        );

        return $outbound;
    }

    /**
     * 현재 상태에서 가능한 액션 — 모바일 하단 액션바가 그대로 렌더한다.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function actions(Outbound $outbound, bool $isWarehouseSide): array
    {
        return match ($outbound->status) {
            OutboundStatus::DRAFT, OutboundStatus::APPROVED => $isWarehouseSide
                ? [['key' => 'pick', 'label' => 'FEFO 피킹']]
                : [],
            OutboundStatus::PICKING => $isWarehouseSide
                ? [['key' => 'ship', 'label' => '배송 시작']]
                : [],
            OutboundStatus::SHIPPED => [['key' => 'deliver', 'label' => '배송 완료(입고 확정)']],
            default => [],
        };
    }
}
