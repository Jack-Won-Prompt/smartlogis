<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settlement;

use App\Http\Controllers\Controller;
use App\Models\MonthlyClosing;
use App\Services\ClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 월 마감 — 본사 전용. 최근 연월별 정산 요약 + 마감/마감취소.
 */
class ClosingController extends Controller
{
    public function data(): JsonResponse
    {
        // 정산이 존재하는 연월들 + 마감 여부/합계
        $months = DB::table('settlements')
            ->select('year_month', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('year_month')->orderByDesc('year_month')->get();

        $closed = MonthlyClosing::pluck('closed_at', 'year_month');

        return response()->json([
            'last_page' => 1,
            'total' => $months->count(),
            'data' => $months->map(fn ($m) => [
                'id' => $m->year_month,
                'year_month' => $m->year_month,
                'settle_count' => (int) $m->cnt,
                'total_amount' => (float) $m->total,
                'closed' => $closed->has($m->year_month),
                'closed_at' => $closed->get($m->year_month)
                    ? Carbon::parse($closed->get($m->year_month))->format('Y-m-d H:i')
                    : null,
            ])->all(),
        ]);
    }

    public function close(Request $request, ClosingService $service): JsonResponse
    {
        $validated = $request->validate(['year_month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $service->close($validated['year_month'], $request->user()?->id);

        return response()->json(['message' => "{$validated['year_month']} 마감 완료"]);
    }

    public function reopen(Request $request, ClosingService $service): JsonResponse
    {
        $validated = $request->validate(['year_month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $service->reopen($validated['year_month']);

        return response()->json(['message' => "{$validated['year_month']} 마감이 취소되었습니다."]);
    }

    public function index(): View
    {
        return view('settlement.closing');
    }
}
