<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Gs1Parser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 바코드 스캔 파싱 API. 스캔 문자열을 GS1 파서로 해석하고 GTIN 으로 제품을 조회한다.
 * SUPPLIER 계정이면 Global Scope 로 자사 제품만 매칭된다.
 */
class BarcodeController extends Controller
{
    public function parse(Request $request, Gs1Parser $parser): JsonResponse
    {
        $validated = $request->validate([
            'scan' => ['required', 'string', 'max:200'],
        ]);

        $data = $parser->parse($validated['scan']);

        $product = null;
        if ($data->hasGtin()) {
            $product = Product::query()
                ->where('gtin', $data->gtin)
                ->where('is_active', true)
                ->first(['id', 'product_code', 'product_name', 'spec', 'unit', 'use_expiry', 'use_lot_control']);
        }

        return response()->json([
            'parsed' => $data->toArray(),
            'matched' => $product !== null,
            'product' => $product,
            'message' => $product !== null
                ? null
                : ($data->hasGtin()
                    ? '등록되지 않은 제품입니다. 제품 매핑이 필요합니다.'
                    : '바코드에서 제품 식별자(GTIN)를 찾지 못했습니다.'),
        ]);
    }
}
