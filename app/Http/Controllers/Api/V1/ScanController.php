<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Models\Product;
use App\Models\ProductLot;
use App\Support\Gs1Parser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 바코드 스캔 — 모바일의 핵심 진입점.
 *
 * 웹의 /api/barcode/parse 가 "파싱 + 제품 매칭"까지만 하는 반면, 모바일은 스캔 직후
 * 곧바로 입고/사용분 명세를 만들어야 하므로 다음까지 한 번에 내려준다.
 *  - GS1 파싱 결과(GTIN/유통기한/Lot/시리얼)
 *  - GTIN 매칭 제품
 *  - 스캔된 Lot 의 등록 여부 + 내 조직 현재고
 *  - 내 조직에서 꺼낼 수 있는 Lot 후보(FEFO 정렬)
 */
class ScanController extends ApiController
{
    public function __invoke(Request $request, Gs1Parser $parser): JsonResponse
    {
        $validated = $request->validate([
            'scan' => ['required', 'string', 'max:200'],
            // 재고를 어느 위치 기준으로 볼지. 미지정 시 내 조직(병원/창고).
            'org_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ], [], ['scan' => '바코드']);

        $user = $request->user();
        $data = $parser->parse($validated['scan']);

        // 재고 위치: 공급사/본사는 위치가 없으므로 명시적으로 지정한 경우만 재고를 본다.
        $locationId = $validated['org_id'] ?? (
            in_array($user->role, [OrgType::WAREHOUSE, OrgType::HOSPITAL], true) ? $user->org_id : null
        );

        $product = null;
        if ($data->hasGtin()) {
            // SupplierProductScope 로 공급사는 자사 제품만 매칭된다.
            $product = Product::query()
                ->where('gtin', $data->gtin)
                ->where('is_active', true)
                ->first();
        }

        if ($product === null) {
            return response()->json([
                'parsed' => $data->toArray(),
                'matched' => false,
                'product' => null,
                'lot' => null,
                'lots' => [],
                'message' => $data->hasGtin()
                    ? '등록되지 않은 제품입니다(GTIN '.$data->gtin.'). 본사에 제품 등록을 요청하세요.'
                    : '바코드에서 제품 식별자(GTIN)를 찾지 못했습니다. 다시 스캔해 주세요.',
            ]);
        }

        $scannedLot = null;
        if ($data->lotNo !== null && $data->lotNo !== '') {
            $lot = ProductLot::query()
                ->where('product_id', $product->id)
                ->where('lot_no', $data->lotNo)
                ->first();

            $scannedLot = [
                'id' => $lot?->id,
                'lot_no' => $data->lotNo,
                'expiry_date' => $lot?->expiry_date?->toDateString() ?? $data->expiryDate?->toDateString(),
                'registered' => $lot !== null,
                'on_hand' => ($lot !== null && $locationId !== null)
                    ? $this->onHand($locationId, $product->id, $lot->id)
                    : 0,
                'days_to_expiry' => $lot?->daysToExpiry(),
            ];
        }

        return response()->json([
            'parsed' => $data->toArray(),
            'matched' => true,
            'product' => [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'spec' => $product->spec,
                'gtin' => $product->gtin,
                'unit' => $product->unit,
                'manufacturer' => $product->manufacturer,
                'storage_type' => $product->storage_type->value,
                'storage_label' => $product->storage_type->label(),
                'sales_price' => (float) $product->sales_price,
                'use_lot_control' => $product->use_lot_control,
                'use_expiry' => $product->use_expiry,
            ],
            'lot' => $scannedLot,
            'lots' => $locationId !== null ? $this->lotsInStock($locationId, $product->id) : [],
            'message' => null,
        ]);
    }

    private function onHand(int $orgId, int $productId, int $lotId): int
    {
        return (int) DB::table('stock_balances')
            ->where('org_id', $orgId)->where('product_id', $productId)->where('lot_id', $lotId)
            ->value('qty');
    }

    /**
     * 해당 위치에서 재고가 남은 Lot 후보 (FEFO 순).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lotsInStock(int $orgId, int $productId): array
    {
        return DB::table('stock_balances as b')
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->where('b.org_id', $orgId)
            ->where('b.product_id', $productId)
            ->where('b.qty', '>', 0)
            ->orderByRaw('CASE WHEN l.expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('l.expiry_date')
            ->select('l.id', 'l.lot_no', 'l.expiry_date', 'b.qty')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'lot_no' => $r->lot_no,
                'expiry_date' => $r->expiry_date,
                'qty' => (int) $r->qty,
            ])->all();
    }
}
