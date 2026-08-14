<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Inbound;
use App\Models\Outbound;
use App\Models\StockReturn;
use App\Services\LabelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QR/바코드 라벨 출력 — 출고(피킹 완료, LOT 배정분)·대량입고 명세 기준.
 */
class LabelController extends Controller
{
    public function outbound(Outbound $outbound, LabelService $svc): View
    {
        $outbound->load(['items.product', 'items.lot']);

        $labels = $outbound->items
            ->filter(fn ($it) => $it->lot_id !== null && $it->lot !== null)   // LOT 배정된 항목만
            ->map(fn ($it) => $svc->label(
                $it->product->product_code,
                $it->product->product_name,
                $it->product->gtin,
                $it->lot->lot_no,
                $it->lot->expiry_date?->toDateString(),
            ))->values()->all();

        return view('labels.print', [
            'title' => "출고 {$outbound->outbound_no} 라벨",
            'labels' => $labels,
        ]);
    }

    public function inbound(Inbound $inbound, LabelService $svc): View
    {
        $inbound->load(['items.product']);

        $labels = $inbound->items
            ->map(fn ($it) => $svc->label(
                $it->product->product_code,
                $it->product->product_name,
                $it->product->gtin,
                $it->lot_no,
                $it->expiry_date?->toDateString(),
            ))->values()->all();

        return view('labels.print', [
            'title' => "입고 {$inbound->inbound_no} 라벨",
            'labels' => $labels,
        ]);
    }

    /** 병원 출고지시서(단건) — 출고번호 QR + 품목 명세. */
    public function outboundOrder(Outbound $outbound, LabelService $svc): View
    {
        return view('documents.order-sheet', [
            'barTitle' => "출고지시서 — {$outbound->outbound_no}",
            'sheets' => [$this->outboundSheet($outbound, $svc)],
        ]);
    }

    /** 병원 출고지시서(다건) — 선택한 출고번호 각각을 한 장씩 출력. */
    public function outboundOrders(Request $request, LabelService $svc): View
    {
        $ids = collect(explode(',', $request->string('ids')->toString()))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values();

        $outbounds = Outbound::whereIn('id', $ids)->orderBy('id')->get();   // 스코프 적용됨
        $sheets = $outbounds->map(fn (Outbound $o) => $this->outboundSheet($o, $svc))->all();

        return view('documents.order-sheet', [
            'barTitle' => '출고지시서 — '.count($sheets).'건',
            'sheets' => $sheets,
        ]);
    }

    /** 반품 출고지시서(단건) — 반품번호 QR + 품목 명세(병원 → 창고). */
    public function returnOrder(StockReturn $return, LabelService $svc): View
    {
        return view('documents.order-sheet', [
            'barTitle' => "반품 출고지시서 — {$return->return_no}",
            'sheets' => [$this->returnSheet($return, $svc)],
        ]);
    }

    /**
     * 출고지시서 1장 데이터.
     *
     * @return array<string, mixed>
     */
    private function outboundSheet(Outbound $outbound, LabelService $svc): array
    {
        $outbound->load(['warehouse', 'hospital', 'items.product', 'items.lot']);

        return [
            'title' => '출고지시서',
            'docNo' => $outbound->outbound_no,
            'qr' => $svc->qrDataUri($outbound->outbound_no, 160),
            'fromLabel' => '출고 창고',
            'fromName' => $outbound->warehouse->name,
            'toLabel' => '납품 병원',
            'toName' => $outbound->hospital->name,
            'meta' => [
                ['출고예정일', $outbound->planned_date?->toDateString() ?? '-'],
                ['구분', $outbound->source_type->label()],
                ['상태', $outbound->status->label()],
            ],
            'items' => $outbound->items->map(fn ($it) => [
                'code' => $it->product->product_code,
                'name' => $it->product->product_name,
                'lot' => $it->lot_id !== null ? $it->lot->lot_no : '미배정',
                'expiry' => $it->lot_id !== null ? ($it->lot->expiry_date?->toDateString() ?? '-') : '-',
                'qty' => (int) $it->qty,
            ])->all(),
            'signLeft' => '창고 담당',
            'signRight' => '병원 인수',
        ];
    }

    /**
     * 반품 출고지시서 1장 데이터.
     *
     * @return array<string, mixed>
     */
    private function returnSheet(StockReturn $return, LabelService $svc): array
    {
        $return->load(['hospital', 'warehouse', 'items.product', 'items.lot']);

        return [
            'title' => '반품 출고지시서',
            'docNo' => $return->return_no,
            'qr' => $svc->qrDataUri($return->return_no, 160),
            'fromLabel' => '반납 병원',
            'fromName' => $return->hospital->name,
            'toLabel' => '입고 창고',
            'toName' => $return->warehouse->name,
            'meta' => [
                ['등록일', $return->created_at?->timezone('Asia/Seoul')?->format('Y-m-d') ?? '-'],
                ['상태', $return->status->label()],
                ['사유', $return->reason ?? '-'],
            ],
            'items' => $return->items->map(fn ($it) => [
                'code' => $it->product->product_code,
                'name' => $it->product->product_name,
                'lot' => $it->lot->lot_no,
                'expiry' => $it->lot->expiry_date?->toDateString() ?? '-',
                'qty' => (int) $it->qty,
            ])->all(),
            'signLeft' => '병원 담당',
            'signRight' => '창고 인수',
        ];
    }
}
