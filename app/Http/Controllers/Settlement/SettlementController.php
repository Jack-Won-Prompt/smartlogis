<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settlement;

use App\Enums\OrgType;
use App\Enums\SettleType;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 월 정산 조회. HQ 전체, 병원은 자사 매출, 공급사는 자사 매입만.
 */
class SettlementController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Settlement::query()->with('organization')
            ->when($request->string('year_month')->toString(), fn ($q, $v) => $q->where('year_month', $v))
            ->when($request->string('settle_type')->toString(), fn ($q, $v) => $q->where('settle_type', $v))
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->orderByDesc('year_month')->orderBy('settle_type');

        // 역할별 스코프
        if ($user->role === OrgType::HOSPITAL) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::SALES->value);
        } elseif ($user->role === OrgType::SUPPLIER) {
            $query->where('org_id', $user->org_id)->where('settle_type', SettleType::PURCHASE->value);
        }

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (Settlement $s) => [
                'id' => $s->id,
                'year_month' => $s->year_month,
                'org_name' => $s->organization->name,
                'settle_type' => $s->settle_type->value,
                'settle_label' => $s->settle_type->label(),
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'total_qty' => $s->total_qty,
                'total_amount' => (float) $s->total_amount,
            ])->all(),
        ]);
    }

    public function index(): View
    {
        return view('settlement.index');
    }
}
