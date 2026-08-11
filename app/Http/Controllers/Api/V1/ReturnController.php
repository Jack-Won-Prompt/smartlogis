<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\ReturnStatus;
use App\Exceptions\DomainException;
use App\Models\StockReturn;
use App\Services\ReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 반납(병원/라이프 → 창고).
 *
 * 등록(REQUESTED) → 배송(SHIPPING) → 수령확인(RECEIVED, 이때 재고가 실제로 이동).
 *
 * 상태 전이는 전부 웹과 같은 ReturnService 에 위임한다. 재고를 건드리는 로직이
 * 두 벌이 되면 웹과 앱의 결과가 어긋난다.
 */
class ReturnController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)
            ->with(['hospital:id,name', 'warehouse:id,name'])
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('return_no', 'like', "%{$v}%"))
            // 창고가 처리해야 할 건(도착 예정 + 배송 중)만 보기.
            ->when($request->boolean('open'), fn ($q) => $q->whereIn('status', [
                ReturnStatus::REQUESTED->value, ReturnStatus::SHIPPING->value,
            ]))
            ->orderByDesc('id');

        $open = (clone $query)->reorder()->whereIn('status', [
            ReturnStatus::REQUESTED->value, ReturnStatus::SHIPPING->value,
        ])->count();

        $summary = $this->statusSummary($query, [
            ReturnStatus::REQUESTED->value => ['등록', 'info'],
            ReturnStatus::SHIPPING->value => ['배송중', 'warn'],
            ReturnStatus::RECEIVED->value => ['수령완료', 'ok'],
            ReturnStatus::CANCELED->value => ['취소', 'hold'],
        ], [
            $this->stat('전체', (clone $query)->reorder()->count(), '건'),
            $this->stat('진행 중', $open, '건', $open > 0 ? 'warn' : 'ok'),
        ]);

        $query->reorder()->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'oldest' => $q->orderBy('id'),
            default => $q->orderByDesc('id'),
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (StockReturn $r) => [
            'id' => $r->id,
            'return_no' => $r->return_no,
            'hospital_name' => $r->hospital?->name,
            'warehouse_name' => $r->warehouse?->name,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'tone' => $this->tone($r->status),
            'reason' => $r->reason,
            'shipped_at' => $r->shipped_at?->toIso8601String(),
            'received_at' => $r->received_at?->toIso8601String(),
            'items_count' => $r->items_count,
            'created_at' => $r->created_at?->toIso8601String(),
        ], $summary);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $return = $this->find($request, $id);

        $return->load([
            'hospital:id,name', 'warehouse:id,name',
            'items.product:id,product_code,product_name,spec,unit',
            'items.lot:id,lot_no,expiry_date',
        ]);

        return response()->json(['data' => [
            'id' => $return->id,
            'return_no' => $return->return_no,
            'hospital_name' => $return->hospital?->name,
            'warehouse_name' => $return->warehouse?->name,
            'status' => $return->status->value,
            'status_label' => $return->status->label(),
            'tone' => $this->tone($return->status),
            'reason' => $return->reason,
            'shipped_at' => $return->shipped_at?->toIso8601String(),
            'received_at' => $return->received_at?->toIso8601String(),
            // 어떤 버튼을 띄울지는 서버가 정한다 — 앱이 상태 규칙을 알 필요가 없다.
            'actions' => $this->actions($request, $return),
            'items' => $return->items->map(fn ($it) => [
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
            ])->all(),
        ]]);
    }

    /** 병원·라이프: 반납 등록. 모바일은 스캔한 Lot 을 그대로 명세로 올린다. */
    public function store(Request $request, ReturnService $service): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'WAREHOUSE')],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_id' => ['required', 'integer', 'exists:product_lots,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ], [], ['warehouse_id' => '반납 창고', 'items' => '반납 명세']);

        try {
            // 병원 ID 는 로그인 사용자의 조직으로 고정한다(위조 방지).
            $return = $service->register(
                $user->org_id,
                (int) $validated['warehouse_id'],
                $validated['items'],
                $user->id,
            );

            if (($validated['reason'] ?? null) !== null) {
                $return->update(['reason' => $validated['reason']]);
            }
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "반납 {$return->return_no} 이(가) 등록되었습니다.",
            'id' => $return->id,
        ], 201);
    }

    /** 배송 시작 — 물품을 창고로 보냈다. */
    public function ship(Request $request, int $id, ReturnService $service): JsonResponse
    {
        $return = $this->find($request, $id);

        try {
            $service->ship($return);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->ok("반납 {$return->return_no} 을(를) 배송 처리했습니다.");
    }

    /** 수령확인(창고/본사) — 여기서 병원 재고가 빠지고 창고 재고가 늘어난다. */
    public function receive(Request $request, int $id, ReturnService $service): JsonResponse
    {
        $return = $this->find($request, $id);

        try {
            $service->receive($return, $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->ok("반납 {$return->return_no} 을(를) 수령 확인했습니다. 재고가 이동되었습니다.");
    }

    public function cancel(Request $request, int $id, ReturnService $service): JsonResponse
    {
        $return = $this->find($request, $id);

        try {
            $service->cancel($return);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->ok("반납 {$return->return_no} 을(를) 취소했습니다.");
    }

    /**
     * 역할별 조회 범위.
     *
     * StockReturn 에는 HospitalScope 가 걸려 있어 병원 계정은 자동으로
     * 자기 건만 본다. 창고는 자기 창고로 오는 건만 봐야 하므로 여기서 좁힌다.
     *
     * @return Builder<StockReturn>
     */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();

        return StockReturn::query()
            ->when($user->role === OrgType::WAREHOUSE,
                fn ($q) => $q->where('warehouse_id', $user->org_id));
    }

    private function find(Request $request, int $id): StockReturn
    {
        $return = $this->scoped($request)->find($id);

        abort_if($return === null, 404, '반납 문서를 찾을 수 없습니다.');

        return $return;
    }

    /**
     * 이 사용자가 지금 할 수 있는 동작.
     *
     * @return array<int, array{key: string, label: string, tone: string}>
     */
    private function actions(Request $request, StockReturn $return): array
    {
        $role = $request->user()->role;
        $isOwner = $return->hospital_id === $request->user()->org_id;

        return match ($return->status) {
            // 등록 직후 — 보낸 쪽이 배송 처리하거나 취소한다.
            ReturnStatus::REQUESTED => array_values(array_filter([
                ($isOwner || $role === OrgType::HQ || $role === OrgType::WAREHOUSE)
                    ? ['key' => 'ship', 'label' => '배송 시작', 'tone' => 'info'] : null,
                ($isOwner || $role === OrgType::HQ)
                    ? ['key' => 'cancel', 'label' => '취소', 'tone' => 'crit'] : null,
            ])),
            // 배송 중 — 창고/본사만 수령확인할 수 있다(재고가 실제로 움직인다).
            ReturnStatus::SHIPPING => ($role === OrgType::HQ || $role === OrgType::WAREHOUSE)
                ? [['key' => 'receive', 'label' => '수령 확인', 'tone' => 'ok']]
                : [],
            default => [],
        };
    }

    private function tone(ReturnStatus $status): string
    {
        return match ($status) {
            ReturnStatus::REQUESTED => 'info',
            ReturnStatus::SHIPPING => 'warn',
            ReturnStatus::RECEIVED => 'ok',
            ReturnStatus::CANCELED => 'hold',
        };
    }
}
