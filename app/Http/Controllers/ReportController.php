<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesChannel;
use App\Enums\UsageStatus;
use App\Models\UsageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 리포트 — 본사(HQ) 전용. 채널별 매출·상품분석 등 승인된 사용분(매출) 기반 집계.
 */
class ReportController extends Controller
{
    public function channelSales(): View
    {
        return view('reports.channel-sales');
    }

    /** 채널별 매출(승인 사용분 기준). */
    public function channelSalesData(Request $request): JsonResponse
    {
        $rows = UsageReport::query()
            ->where('status', UsageStatus::APPROVED)
            ->when($request->string('date_from')->toString(), fn ($q, $v) => $q->whereDate('usage_date', '>=', $v))
            ->when($request->string('date_to')->toString(), fn ($q, $v) => $q->whereDate('usage_date', '<=', $v))
            ->groupBy('sales_channel')
            ->selectRaw('sales_channel, COUNT(*) as cnt, SUM(total_amount) as amount')
            ->get();

        $total = (float) $rows->sum('amount');

        // 모든 채널을 0 포함해 표기(빠진 채널도 노출).
        $byChannel = $rows->keyBy('sales_channel');
        $data = collect(SalesChannel::cases())->map(function (SalesChannel $ch) use ($byChannel, $total) {
            $row = $byChannel->get($ch->value);
            $amount = (float) ($row->amount ?? 0);

            return [
                'channel' => $ch->value,
                'channel_label' => $ch->label(),
                'cnt' => (int) ($row->cnt ?? 0),
                'amount' => $amount,
                'share' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        })->sortByDesc('amount')->values();

        return response()->json([
            'total' => $total,
            'total_cnt' => (int) $rows->sum('cnt'),
            'data' => $data->all(),
        ]);
    }
}
