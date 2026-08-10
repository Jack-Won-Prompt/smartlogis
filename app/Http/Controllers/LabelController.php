<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Inbound;
use App\Models\Outbound;
use App\Services\LabelService;
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
}
