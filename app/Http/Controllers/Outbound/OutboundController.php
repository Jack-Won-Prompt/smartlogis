<?php

declare(strict_types=1);

namespace App\Http\Controllers\Outbound;

use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Http\Controllers\Controller;
use App\Models\Outbound;
use App\Services\DocumentNoService;
use App\Services\InboundService;
use App\Services\OutboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 출고 — 지시 등록 + 승인 + FEFO 피킹 + 배송 + 완료(병원 입고).
 */
class OutboundController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $query = Outbound::query()->with(['warehouse', 'hospital', 'deliveryProof.photos'])->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('outbound_no', 'like', "%{$v}%"))
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
            ->when($request->string('mode')->toString() === 'picking', fn ($q) => $q->whereIn('status', [OutboundStatus::DRAFT->value, OutboundStatus::APPROVED->value, OutboundStatus::PICKING->value]))
            ->when($request->string('mode')->toString() === 'delivery', fn ($q) => $q->whereIn('status', [OutboundStatus::SHIPPED->value, OutboundStatus::DELIVERED->value]))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (Outbound $o) => [
                'id' => $o->id,
                'outbound_no' => $o->outbound_no,
                'warehouse_name' => $o->warehouse->name,
                'hospital_name' => $o->hospital->name,
                'source_type' => $o->source_type->value,
                'source_label' => $o->source_type->label(),
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'planned_date' => $o->planned_date?->toDateString(),
                'items_count' => $o->items_count,
                // 모바일 배송 처리에서 올라온 현장 증빙. 리스트에서 바로 확인한다.
                'delivered' => $o->status === OutboundStatus::DELIVERED,
                'delivered_at' => $o->delivered_at?->timezone('Asia/Seoul')?->format('Y-m-d H:i'),
                'signer_name' => $o->deliveryProof?->signer_name,
                'signature_url' => $o->deliveryProof?->signatureUrl(),
                'photos' => ($o->deliveryProof?->photos ?? collect())
                    ->map(fn ($ph) => ['id' => $ph->id, 'url' => $ph->url(), 'name' => $ph->file_name])
                    ->values()->all(),
            ])->all(),
        ]);
    }

    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'WAREHOUSE')],
            'hospital_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'HOSPITAL')],
            'planned_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ], [], ['warehouse_id' => '창고', 'hospital_id' => '병원', 'planned_date' => '출고예정일']);

        $outbound = DB::transaction(function () use ($validated, $docNo, $request) {
            $outbound = Outbound::create([
                'outbound_no' => $docNo->next('OB'),
                'warehouse_id' => $validated['warehouse_id'],
                'hospital_id' => $validated['hospital_id'],
                'status' => OutboundStatus::APPROVED,
                'source_type' => OutboundSourceType::MANUAL,
                'planned_date' => $validated['planned_date'],
                'created_by' => $request->user()?->id,
            ]);
            foreach ($validated['items'] as $item) {
                $outbound->items()->create(['product_id' => $item['product_id'], 'qty' => $item['qty']]);
            }

            return $outbound;
        });

        return response()->json(['id' => $outbound->id, 'outbound_no' => $outbound->outbound_no]);
    }

    public function show(Outbound $outbound): JsonResponse
    {
        $outbound->load(['warehouse', 'hospital', 'items.product', 'items.lot']);

        return response()->json([
            'id' => $outbound->id,
            'outbound_no' => $outbound->outbound_no,
            'status' => $outbound->status->value,
            'status_label' => $outbound->status->label(),
            'source_label' => $outbound->source_type->label(),
            'planned_date' => $outbound->planned_date?->toDateString(),
            'shipped_at' => $outbound->shipped_at?->timezone('Asia/Seoul')?->format('Y-m-d H:i'),
            'delivered_at' => $outbound->delivered_at?->timezone('Asia/Seoul')?->format('Y-m-d H:i'),
            'warehouse_name' => $outbound->warehouse->name,
            'hospital_name' => $outbound->hospital->name,
            'items' => $outbound->items->map(fn ($it) => [
                'id' => $it->id,
                'product_code' => $it->product->product_code,
                'product_name' => $it->product->product_name,
                'lot_no' => $it->lot?->lot_no,
                'lot_assigned' => $it->lot_id !== null,
                'qty' => $it->qty,
            ])->all(),
        ]);
    }

    public function pick(Outbound $outbound, OutboundService $service, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);
        $itemIds = $validated['item_ids'] ?? null;
        $service->pick($outbound, $request->user()?->id, $itemIds !== null && $itemIds !== [] ? $itemIds : null);

        $remaining = $outbound->fresh()?->items()->whereNull('lot_id')->count() ?? 0;
        $msg = $remaining > 0
            ? "{$outbound->outbound_no} 선택 품목 피킹 완료 — 미피킹 {$remaining}건 남음."
            : "{$outbound->outbound_no} FEFO 피킹 완료 — 창고 재고가 차감되었습니다.";

        return response()->json(['message' => $msg]);
    }

    public function ship(Outbound $outbound, OutboundService $service): JsonResponse
    {
        $service->ship($outbound);

        return response()->json(['message' => "{$outbound->outbound_no} 배송을 시작했습니다."]);
    }

    public function deliver(Outbound $outbound, OutboundService $service, InboundService $inboundService, Request $request): JsonResponse
    {
        $service->deliver($outbound, $inboundService, $request->user()?->id);

        return response()->json(['message' => "{$outbound->outbound_no} 배송 완료 — 병원 재고에 반영되었습니다."]);
    }

    /** 목록에서 상태값 인라인 변경 저장(수동 보정). 재고 로직 없이 상태 컬럼만 갱신. */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'updated' => ['array'],
            'updated.*.id' => ['required', 'integer'],
            'updated.*.status' => ['required', Rule::in(array_column(OutboundStatus::cases(), 'value'))],
        ]);

        $n = 0;
        foreach ($data['updated'] ?? [] as $row) {
            $ob = Outbound::find((int) $row['id']);
            if ($ob === null) {
                continue;
            }
            $ob->update(['status' => $row['status']]);
            $n++;
        }

        return response()->json(['updated' => $n, 'message' => "{$n}건 상태를 저장했습니다."]);
    }

    public function index(Request $request): View
    {
        // 라우트에 따라 모드 구분: 출고 지시 / 피킹·출고 / 배송 현황
        $mode = match ($request->route()?->getName()) {
            'outbounds.picking' => 'picking',
            'outbounds.delivery' => 'delivery',
            default => 'order',
        };

        return view('outbound.index', ['mode' => $mode]);
    }
}
