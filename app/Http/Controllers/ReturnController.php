<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrgType;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\StockReturn;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 반납(병원 → 창고). 등록(병원/라이프) → 배송 → 수령확인(창고/본사, 재고 복귀).
 * 병원 계정은 HospitalScope 로 자기 반납만 조회한다.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $service) {}

    public function index(): View
    {
        return view('returns.index', [
            'hospitals' => Organization::query()->where('org_type', OrgType::HOSPITAL)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockReturn::query()
            ->with(['hospital:id,name', 'warehouse:id,name'])
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 20), 1), 200);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (StockReturn $r) => [
                'id' => $r->id,
                'return_no' => $r->return_no,
                'hospital' => $r->hospital?->name,
                'warehouse' => $r->warehouse?->name,
                'items' => $r->items_count,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'reason' => $r->reason,
                'created_at' => $r->created_at?->timezone('Asia/Seoul')->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    public function store(Request $request, ReturnService $service): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'hospital_id' => 'nullable|integer|exists:organizations,id',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.lot_id' => 'required|integer|exists:product_lots,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        // 병원 계정은 자기 병원, 라이프/본사는 대상 병원 선택.
        $hospitalId = $user->isHospital() ? (int) $user->org_id : (int) ($data['hospital_id'] ?? 0);
        if ($hospitalId <= 0) {
            return response()->json(['message' => '반납할 병원을 선택하세요.'], 422);
        }

        // 반납 입고 창고 — 단일 중앙창고 기준(활성 창고 중 첫 번째).
        $warehouseId = (int) Organization::query()->where('org_type', OrgType::WAREHOUSE)
            ->where('is_active', true)->orderBy('id')->value('id');
        if ($warehouseId <= 0) {
            return response()->json(['message' => '반납받을 창고가 없습니다.'], 422);
        }

        try {
            $return = $service->register($hospitalId, $warehouseId, array_map(fn ($i) => [
                'product_id' => (int) $i['product_id'],
                'lot_id' => (int) $i['lot_id'],
                'qty' => (int) $i['qty'],
            ], $data['items']), $user->id);
            if (isset($data['reason'])) {
                $return->update(['reason' => $data['reason']]);
            }
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json(['ok' => true, 'return_no' => $return->return_no]);
    }

    public function ship(StockReturn $return): JsonResponse
    {
        return $this->transition(fn () => $this->service->ship($return));
    }

    public function receive(Request $request, StockReturn $return): JsonResponse
    {
        return $this->transition(fn () => $this->service->receive($return, $request->user()->id));
    }

    public function cancel(StockReturn $return): JsonResponse
    {
        return $this->transition(fn () => $this->service->cancel($return));
    }

    /** @param  callable():StockReturn  $action */
    private function transition(callable $action): JsonResponse
    {
        try {
            $return = $action();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json(['ok' => true, 'status' => $return->status->value, 'status_label' => $return->status->label()]);
    }
}
