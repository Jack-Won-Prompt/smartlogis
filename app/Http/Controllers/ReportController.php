<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesChannel;
use App\Enums\UsageStatus;
use App\Models\Product;
use App\Models\UsageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
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

    public function productAnalysis(): View
    {
        return view('reports.product-analysis');
    }

    /** 상품분석 — 품목별 사용량·매출·현재고·회전(사용량/현재고). */
    public function productAnalysisData(Request $request): JsonResponse
    {
        // 승인 사용분의 품목별 사용량·매출.
        $usage = DB::table('usage_report_items as ui')
            ->join('usage_reports as ur', 'ur.id', '=', 'ui.usage_report_id')
            ->where('ur.status', UsageStatus::APPROVED->value)
            ->when($request->string('date_from')->toString(), fn ($q, $v) => $q->whereDate('ur.usage_date', '>=', $v))
            ->when($request->string('date_to')->toString(), fn ($q, $v) => $q->whereDate('ur.usage_date', '<=', $v))
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('ur.hospital_id', $v))
            ->groupBy('ui.product_id')
            ->selectRaw('ui.product_id, SUM(ui.qty) as used_qty, SUM(ui.amount) as amount')
            ->get()
            ->keyBy('product_id');

        // 품목별 현재고(전 위치 합).
        $stock = DB::table('stock_balances')
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('org_id', $v))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) as stock')->pluck('stock', 'product_id');

        $kw = $request->string('keyword')->toString();
        $products = Product::query()
            ->when($kw !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('product_name', 'like', "%{$kw}%")->orWhere('product_code', 'like', "%{$kw}%")))
            ->get(['id', 'product_code', 'product_name']);

        $data = $products->map(function (Product $p) use ($usage, $stock) {
            $used = (int) ($usage[$p->id]->used_qty ?? 0);
            $amount = (float) ($usage[$p->id]->amount ?? 0);
            $onHand = (int) ($stock[$p->id] ?? 0);

            return [
                'product_code' => $p->product_code,
                'product_name' => $p->product_name,
                'used_qty' => $used,
                'amount' => $amount,
                'stock' => $onHand,
                'turnover' => $onHand > 0 ? round($used / $onHand, 2) : null,
            ];
        })->sortByDesc('amount')->values();

        return response()->json([
            'total_amount' => (float) $data->sum('amount'),
            'total_used' => (int) $data->sum('used_qty'),
            'data' => $data->all(),
        ]);
    }
}
