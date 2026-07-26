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
            ->orderByDesc('year_month')
            ->orderBy('settle_type');

        if ($user->role === OrgType::HOSPITAL) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::SALES->value);
        } elseif ($user->role === OrgType::SUPPLIER) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::PURCHASE->value);
        }

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
        ]);
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
