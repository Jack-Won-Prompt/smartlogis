<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbound;

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Http\Controllers\Controller;
use App\Models\Inbound;
use App\Services\DocumentNoService;
use App\Services\InboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 입고 — ASN 등록 + 검수/확정. 확정은 InboundService(재고 반영)에 위임한다.
 */
class InboundController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $query = Inbound::query()->with(['fromOrg', 'toOrg'])->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('direction')->toString(), fn ($q, $v) => $q->where('direction', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('inbound_no', 'like', "%{$v}%"))
            ->when($request->boolean('receivable'), fn ($q) => $q->whereIn('status', [InboundStatus::PLANNED->value, InboundStatus::RECEIVING->value]))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (Inbound $i) => [
                'id' => $i->id,
                'inbound_no' => $i->inbound_no,
                'direction' => $i->direction->value,
                'direction_label' => $i->direction->label(),
                'from_name' => $i->fromOrg->name,
                'to_name' => $i->toOrg->name,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'planned_date' => $i->planned_date?->toDateString(),
                'items_count' => $i->items_count,
            ])->all(),
        ]);
    }

    /** ASN 등록(공급사→창고). 명세 포함. */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $validated = $request->validate([
            'from_org_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'SUPPLIER')],
            'to_org_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'WAREHOUSE')],
            'planned_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_no' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], [], ['from_org_id' => '공급사', 'to_org_id' => '창고', 'planned_date' => '입고예정일']);

        $inbound = DB::transaction(function () use ($validated, $docNo, $request) {
            $inbound = Inbound::create([
                'inbound_no' => $docNo->next('IB'),
                'direction' => InboundDirection::SUPPLIER_TO_WH,
                'from_org_id' => $validated['from_org_id'],
                'to_org_id' => $validated['to_org_id'],
                'status' => InboundStatus::PLANNED,
                'planned_date' => $validated['planned_date'],
                'created_by' => $request->user()?->id,
            ]);
            foreach ($validated['items'] as $item) {
                $inbound->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_no' => $item['lot_no'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'] ?? 0,
                ]);
            }

            return $inbound;
        });

        return response()->json(['id' => $inbound->id, 'inbound_no' => $inbound->inbound_no]);
    }

    /** 상세(검수 화면용). */
    public function show(Inbound $inbound): JsonResponse
    {
        $inbound->load(['fromOrg', 'toOrg', 'items.product']);

        return response()->json([
            'id' => $inbound->id,
            'inbound_no' => $inbound->inbound_no,
            'status' => $inbound->status->value,
            'status_label' => $inbound->status->label(),
            'from_name' => $inbound->fromOrg->name,
            'to_name' => $inbound->toOrg->name,
            'items' => $inbound->items->map(fn ($it) => [
                'id' => $it->id,
                'product_code' => $it->product->product_code,
                'product_name' => $it->product->product_name,
                'lot_no' => $it->lot_no,
                'expiry_date' => $it->expiry_date?->toDateString(),
                'qty' => $it->qty,
                'scanned' => $it->scanned_barcode !== null,
            ])->all(),
        ]);
    }

    /** 확정 → 재고 반영. */
    public function confirm(Inbound $inbound, InboundService $service, Request $request): JsonResponse
    {
        $service->confirm($inbound, $request->user()?->id);

        return response()->json(['message' => "입고 {$inbound->inbound_no} 확정 완료 — 재고에 반영되었습니다."]);
    }

    /** 입고 삭제 — 확정(재고 반영) 전 문서만. 확정 건은 재고 무결성 위해 차단. */
    public function destroy(Inbound $inbound): JsonResponse
    {
        if ($inbound->status === InboundStatus::CONFIRMED) {
            return response()->json(['message' => '이미 확정(재고 반영)된 입고는 삭제할 수 없습니다.'], 409);
        }

        $no = $inbound->inbound_no;
        $inbound->items()->delete();
        $inbound->delete();

        return response()->json(['message' => "입고 {$no} 을(를) 삭제했습니다."]);
    }

    public function asn(): View
    {
        return view('inbound.asn');
    }

    public function receiving(): View
    {
        return view('inbound.receiving');
    }
}
