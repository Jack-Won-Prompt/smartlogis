<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Enums\UsageStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\UsageReport;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * B-5b 상품분석 — 품목별 사용량·매출·현재고·회전.
 */
it('품목별 사용량·매출·현재고·회전을 집계한다', function () {
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create(['sales_price' => 100]);
    $lot = ProductLot::factory()->create(['product_id' => $product->id]);

    // 현재고 10
    DB::transaction(fn () => app(StockService::class)->apply(
        TxType::IN_HOSPITAL, $hospital->id, $product->id, $lot->id, 10, RefType::INBOUND
    ));

    // 승인 사용분: 5개, 매출 500
    $report = UsageReport::factory()->create([
        'hospital_id' => $hospital->id, 'status' => UsageStatus::APPROVED,
        'usage_date' => now()->toDateString(), 'total_amount' => 500,
    ]);
    $report->items()->create(['product_id' => $product->id, 'lot_id' => $lot->id, 'qty' => 5, 'unit_price' => 100, 'amount' => 500]);

    actingAsRole(OrgType::HQ);
    $res = $this->getJson('/reports/product-analysis/data')->assertOk();

    $row = collect($res->json('data'))->firstWhere('product_code', $product->product_code);
    expect($row)->not->toBeNull();
    expect($row['used_qty'])->toBe(5);
    expect((float) $row['amount'])->toEqual(500.0);
    expect($row['stock'])->toBe(10);
    expect((float) $row['turnover'])->toEqual(0.5);
});
