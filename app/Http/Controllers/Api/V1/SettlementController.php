<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\SettleType;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 월 정산 조회. HQ 전체 / 병원은 자사 매출 / 공급사는 자사 매입만 본다.
 */
class SettlementController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Settlement::query()
            ->with('organization:id,name')
            ->when($request->string('year_month')->toString(), fn ($q, $v) => $q->where('year_month', $v))
            ->when($request->string('settle_type')->toString(), fn ($q, $v) => $q->where('settle_type', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            // 병원 필터. 정산의 상대는 org_id 한 컬럼(병원=매출, 공급사=매입)이므로 여기로 매핑한다.
            // 아래 역할 스코프가 뒤이어 걸리므로 남의 병원을 지정해도 결과는 비게 된다.
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('org_id', $v));

        if ($user->role === OrgType::HOSPITAL) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::SALES->value);
        } elseif ($user->role === OrgType::SUPPLIER) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::PURCHASE->value);
        }

        // 정산은 금액이 핵심이다. 목록만 나열하면 "이번에 얼마인지" 를 알 수 없어
        // 매출·매입 합계와 미확정 건수를 함께 내려준다.
        $agg = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN settle_type = ? THEN total_amount ELSE 0 END), 0) as sales,
                COALESCE(SUM(CASE WHEN settle_type = ? THEN total_amount ELSE 0 END), 0) as purchase,
                SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed_cnt,
                SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) as closed_cnt,
                SUM(CASE WHEN status NOT IN ('CONFIRMED','CLOSED') THEN 1 ELSE 0 END) as open_cnt
            ", [SettleType::SALES->value, SettleType::PURCHASE->value])
            ->first();

        $sales = (float) ($agg->sales ?? 0);
        $purchase = (float) ($agg->purchase ?? 0);
        $openCnt = (int) ($agg->open_cnt ?? 0);

        // 역할에 따라 보이는 금액이 다르다 — 병원은 매출만, 공급사는 매입만 본다.
        $stats = match ($user->role) {
            OrgType::HOSPITAL => [
                $this->stat('매출 합계', $this->wonShort($sales), null, 'info'),
                $this->stat('미확정', $openCnt, '건', $openCnt > 0 ? 'warn' : 'ok'),
            ],
            OrgType::SUPPLIER => [
                $this->stat('매입 합계', $this->wonShort($purchase), null, 'info'),
                $this->stat('미확정', $openCnt, '건', $openCnt > 0 ? 'warn' : 'ok'),
            ],
            default => [
                $this->stat('매출', $this->wonShort($sales), null, 'info'),
                $this->stat('매입', $this->wonShort($purchase), null, 'hold'),
                $this->stat('미확정', $openCnt, '건', $openCnt > 0 ? 'warn' : 'ok'),
            ],
        };

        $summary = [
            'stats' => $stats,
            'segments' => [
                $this->segment('확정', (int) ($agg->confirmed_cnt ?? 0), 'ok'),
                $this->segment('마감', (int) ($agg->closed_cnt ?? 0), 'hold'),
                $this->segment('미확정', $openCnt, 'warn'),
            ],
        ];

        $query->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'amount_desc' => $q->orderByDesc('total_amount'),
            'amount_asc' => $q->orderBy('total_amount'),
            'oldest' => $q->orderBy('year_month')->orderBy('settle_type'),
            default => $q->orderByDesc('year_month')->orderBy('settle_type'),
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (Settlement $s) => [
            'id' => $s->id,
            'year_month' => $s->year_month,
            'org_name' => $s->organization?->name,
            'settle_type' => $s->settle_type->value,
            'settle_label' => $s->settle_type->label(),
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'tone' => match ($s->status->value) {
                'CONFIRMED' => 'ok',
                'CLOSED' => 'hold',
                default => 'info',
            },
            'total_qty' => (int) $s->total_qty,
            'total_amount' => (float) $s->total_amount,
        ], $summary);
    }

    /** 정산 상세 — 품목별 집계. */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $settlement = Settlement::query()->with('organization:id,name')->find($id);

        abort_if($settlement === null, 404, '정산 문서를 찾을 수 없습니다.');

        if ($user->role !== OrgType::HQ) {
            abort_unless($settlement->org_id === $user->org_id, 403, '조회 권한이 없습니다.');
        }

        $items = DB::table('settlement_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.settlement_id', $settlement->id)
            ->groupBy('p.id', 'p.product_code', 'p.product_name', 'p.unit')
            ->select(
                'p.product_code', 'p.product_name', 'p.unit',
                DB::raw('SUM(si.qty) as qty'),
                DB::raw('SUM(si.amount) as amount'),
            )
            ->orderByDesc(DB::raw('SUM(si.amount)'))
            ->get();

        return response()->json(['data' => [
            'id' => $settlement->id,
            'year_month' => $settlement->year_month,
            'org_name' => $settlement->organization?->name,
            'settle_type' => $settlement->settle_type->value,
            'settle_label' => $settlement->settle_type->label(),
            'status_label' => $settlement->status->label(),
            'total_qty' => (int) $settlement->total_qty,
            'total_amount' => (float) $settlement->total_amount,
            'items' => $items->map(fn ($r) => [
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'unit' => $r->unit,
                'qty' => (int) $r->qty,
                'amount' => (float) $r->amount,
            ])->all(),
        ]]);
    }
}
