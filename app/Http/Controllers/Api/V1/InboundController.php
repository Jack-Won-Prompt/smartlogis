<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\OrgType;
use App\Exceptions\DomainException;
use App\Models\Inbound;
use App\Models\Product;
use App\Services\DocumentNoService;
use App\Services\InboundService;
use App\Support\Gs1Parser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 입고 — 공급사 ASN 등록 → 창고 스캔 검수 → 확정(재고 반영).
 *
 * Inbound 모델에는 Global Scope 가 없으므로(웹은 역할 미들웨어로만 막는다)
 * 모바일에서는 이 컨트롤러가 from/to 조직 기준 스코프를 명시적으로 강제한다.
 */
class InboundController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)
            ->with(['fromOrg:id,name', 'toOrg:id,name'])
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('direction')->toString(), fn ($q, $v) => $q->where('direction', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('inbound_no', 'like', "%{$v}%"))
            ->when($request->boolean('receivable'), fn ($q) => $q->whereIn('status', [
                InboundStatus::PLANNED->value, InboundStatus::RECEIVING->value,
            ]))
            ->orderByDesc('id');

        // 검수해야 할 게 몇 건인지가 이 화면의 핵심이라 앞에 세운다.
        $receivable = (clone $query)->reorder()->whereIn('status', [
            InboundStatus::PLANNED->value, InboundStatus::RECEIVING->value,
        ])->count();

        $summary = $this->statusSummary($query, [
            InboundStatus::PLANNED->value => ['예정', 'info'],
            InboundStatus::RECEIVING->value => ['검수중', 'warn'],
            InboundStatus::CONFIRMED->value => ['확정', 'ok'],
        ], [
            $this->stat('전체', (clone $query)->reorder()->count(), '건'),
            $this->stat('검수 대기', $receivable, '건', $receivable > 0 ? 'warn' : 'ok'),
        ]);

        $query->reorder()->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'oldest' => $q->orderBy('id'),
            'planned' => $q->orderBy('planned_date')->orderByDesc('id'),
            default => $q->orderByDesc('id'),   // 최신순
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (Inbound $i) => [
            'id' => $i->id,
            'inbound_no' => $i->inbound_no,
            'direction' => $i->direction->value,
            'direction_label' => $i->direction->label(),
            'from_name' => $i->fromOrg?->name,
            'to_name' => $i->toOrg?->name,
            'status' => $i->status->value,
            'status_label' => $i->status->label(),
            'tone' => $i->status->tone()->value,
            'planned_date' => $i->planned_date?->toDateString(),
            'confirmed_at' => $i->confirmed_at?->toIso8601String(),
            'items_count' => $i->items_count,
        ], $summary);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $inbound = $this->find($request, $id);

        $inbound->load(['fromOrg:id,name', 'toOrg:id,name', 'items.product:id,product_code,product_name,spec,unit,gtin']);

        return response()->json(['data' => [
            'id' => $inbound->id,
            'inbound_no' => $inbound->inbound_no,
            'direction' => $inbound->direction->value,
            'direction_label' => $inbound->direction->label(),
            'status' => $inbound->status->value,
            'status_label' => $inbound->status->label(),
            'tone' => $inbound->status->tone()->value,
            'from_name' => $inbound->fromOrg?->name,
            'to_name' => $inbound->toOrg?->name,
            'to_org_id' => $inbound->to_org_id,
            'planned_date' => $inbound->planned_date?->toDateString(),
            'confirmed_at' => $inbound->confirmed_at?->toIso8601String(),
            'memo' => $inbound->memo,
            'can_confirm' => $this->canConfirm($request, $inbound),
            'items' => $inbound->items->map(fn ($it) => [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'product_code' => $it->product?->product_code,
                'product_name' => $it->product?->product_name,
                'spec' => $it->product?->spec,
                'unit' => $it->product?->unit,
                'gtin' => $it->product?->gtin,
                'lot_no' => $it->lot_no,
                'expiry_date' => $it->expiry_date?->toDateString(),
                'qty' => $it->qty,
                'unit_price' => (float) $it->unit_price,
                'scanned' => $it->scanned_barcode !== null,
                'scanned_barcode' => $it->scanned_barcode,
            ])->all(),
        ]]);
    }

    /** 공급사(또는 본사): ASN 등록. 모바일은 스캔으로 명세를 만든다. */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'from_org_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'SUPPLIER')],
            'to_org_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'WAREHOUSE')],
            'planned_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_no' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.scanned_barcode' => ['nullable', 'string', 'max:200'],
        ], [], [
            'to_org_id' => '입고 창고',
            'planned_date' => '입고예정일',
            'items' => '입고 명세',
        ]);

        // 공급사 계정은 from_org 를 자기 조직으로 고정한다(위조 방지).
        $fromOrgId = $user->role === OrgType::SUPPLIER
            ? $user->org_id
            : ($validated['from_org_id'] ?? null);

        if ($fromOrgId === null) {
            return response()->json(['message' => '공급사를 선택해 주세요.'], 422);
        }

        $inbound = DB::transaction(function () use ($validated, $fromOrgId, $docNo, $user) {
            $inbound = Inbound::create([
                'inbound_no' => $docNo->next('IB'),
                'direction' => InboundDirection::SUPPLIER_TO_WH,
                'from_org_id' => $fromOrgId,
                'to_org_id' => $validated['to_org_id'],
                'status' => InboundStatus::PLANNED,
                'planned_date' => $validated['planned_date'],
                'memo' => $validated['memo'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);

                $inbound->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_no' => $item['lot_no'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'qty' => $item['qty'],
                    'unit_price' => (float) ($product?->purchase_price ?? 0),
                    'scanned_barcode' => $item['scanned_barcode'] ?? null,
                ]);
            }

            return $inbound;
        });

        return response()->json([
            'message' => "입고 예정 {$inbound->inbound_no} 이(가) 등록되었습니다.",
            'id' => $inbound->id,
            'inbound_no' => $inbound->inbound_no,
        ], 201);
    }

    /**
     * 창고 검수 스캔 — 스캔한 바코드로 명세를 대조한다.
     *
     * 예정 명세에 같은 (제품, Lot) 이 있으면 검수 완료로 표시하고,
     * 없으면 "예정 외 입고"로 명세를 추가한다(현장에서 실제로 자주 발생).
     */
    public function scanItem(Request $request, int $id, Gs1Parser $parser): JsonResponse
    {
        $inbound = $this->find($request, $id);

        if ($inbound->status === InboundStatus::CONFIRMED) {
            throw DomainException::conflict('이미 확정된 입고는 검수할 수 없습니다.');
        }
        if ($inbound->status === InboundStatus::CANCELED) {
            throw new DomainException('취소된 입고입니다.');
        }

        $validated = $request->validate([
            'scan' => ['required', 'string', 'max:200'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ], [], ['scan' => '바코드']);

        $data = $parser->parse($validated['scan']);

        if (! $data->hasGtin()) {
            return response()->json(['message' => '바코드에서 GTIN 을 찾지 못했습니다.'], 422);
        }

        $product = Product::query()->where('gtin', $data->gtin)->where('is_active', true)->first();

        if ($product === null) {
            return response()->json([
                'message' => "등록되지 않은 제품입니다(GTIN {$data->gtin}). 본사에 제품 등록을 요청하세요.",
            ], 422);
        }

        $lotNo = $data->lotNo ?? 'NOLOT';
        $qty = $validated['qty'] ?? 1;

        $item = $inbound->items()
            ->where('product_id', $product->id)
            ->where('lot_no', $lotNo)
            ->first();

        $unplanned = false;

        if ($item === null) {
            $item = $inbound->items()->create([
                'product_id' => $product->id,
                'lot_no' => $lotNo,
                'expiry_date' => $data->expiryDate?->toDateString(),
                'qty' => $qty,
                'unit_price' => (float) $product->purchase_price,
                'scanned_barcode' => $data->raw,
            ]);
            $unplanned = true;
        } else {
            $item->update(['scanned_barcode' => $data->raw]);
        }

        if ($inbound->status === InboundStatus::PLANNED) {
            $inbound->update(['status' => InboundStatus::RECEIVING]);
        }

        return response()->json([
            'message' => $unplanned
                ? "예정에 없던 품목입니다. {$product->product_name} (Lot {$lotNo}) 을(를) 명세에 추가했습니다."
                : "{$product->product_name} (Lot {$lotNo}) 검수 확인",
            'unplanned' => $unplanned,
            'item' => [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'lot_no' => $item->lot_no,
                'expiry_date' => $item->expiry_date?->toDateString(),
                'qty' => $item->qty,
                'scanned' => true,
            ],
        ]);
    }

    /** 검수 수량 보정. */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $inbound = $this->find($request, $id);

        if ($inbound->status === InboundStatus::CONFIRMED) {
            throw DomainException::conflict('이미 확정된 입고는 수정할 수 없습니다.');
        }

        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ], [], ['qty' => '수량']);

        $item = $inbound->items()->findOrFail($itemId);
        $item->update(['qty' => $validated['qty']]);

        return $this->ok('수량이 수정되었습니다.');
    }

    public function destroyItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $inbound = $this->find($request, $id);

        if ($inbound->status === InboundStatus::CONFIRMED) {
            throw DomainException::conflict('이미 확정된 입고는 수정할 수 없습니다.');
        }

        $inbound->items()->where('id', $itemId)->delete();

        return $this->ok('명세에서 삭제되었습니다.');
    }

    /** 확정 → 재고 반영 (창고 또는 본사). */
    public function confirm(Request $request, int $id, InboundService $service): JsonResponse
    {
        $inbound = $this->find($request, $id);

        if (! $this->canConfirm($request, $inbound)) {
            return response()->json(['message' => '이 입고를 확정할 권한이 없습니다.'], 403);
        }

        $service->confirm($inbound, $request->user()->id);

        return $this->ok("입고 {$inbound->inbound_no} 확정 완료 — 재고에 반영되었습니다.");
    }

    // ---------------------------------------------------------------- 스코프

    /** @return Builder<Inbound> */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        $query = Inbound::query();

        return match ($user->role) {
            OrgType::HQ => $query,
            OrgType::SUPPLIER => $query->where('from_org_id', $user->org_id),
            // 창고·병원은 자기에게 들어오는 입고 + 자기가 보낸 입고 모두 본다.
            default => $query->where(fn (Builder $q) => $q
                ->where('to_org_id', $user->org_id)
                ->orWhere('from_org_id', $user->org_id)),
        };
    }

    private function find(Request $request, int $id): Inbound
    {
        $inbound = $this->scoped($request)->find($id);

        abort_if($inbound === null, 404, '입고 문서를 찾을 수 없습니다.');

        return $inbound;
    }

    /** 확정은 "받는 쪽"만 할 수 있다(창고/병원). 본사는 대리 확정 가능. */
    private function canConfirm(Request $request, Inbound $inbound): bool
    {
        $user = $request->user();

        if ($inbound->status === InboundStatus::CONFIRMED || $inbound->status === InboundStatus::CANCELED) {
            return false;
        }

        return $user->role === OrgType::HQ || $inbound->to_org_id === $user->org_id;
    }
}
