<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\UsageStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\SafetyStock;
use App\Models\UsageReport;

/**
 * B-6 안전재고 자동추천 — 최근 N개월 승인 사용량의 월평균 × 안전계수.
 */
it('안전재고를 최근 3개월 승인 사용량 월평균으로 추천 적용한다', function () {
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();
    $lot = ProductLot::factory()->create(['product_id' => $product->id]);
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $product->id, 'safety_qty' => 0, 'max_qty' => 0, 'reorder_qty' => 0]);

    // 최근 3개월 내 승인 사용량 합계 30 → 월평균 10 → 계수 1.0 → 안전재고 10
    $mkUsage = function (int $daysAgo, int $qty) use ($hospital, $product, $lot) {
        $r = UsageReport::factory()->create([
            'hospital_id' => $hospital->id, 'status' => UsageStatus::APPROVED,
            'usage_date' => now()->subDays($daysAgo)->toDateString(), 'total_amount' => 0,
        ]);
        $r->items()->create(['product_id' => $product->id, 'lot_id' => $lot->id, 'qty' => $qty, 'unit_price' => 0, 'amount' => 0]);
    };
    $mkUsage(10, 10);
    $mkUsage(40, 10);
    $mkUsage(70, 10);
    $mkUsage(200, 999);   // 3개월 밖 — 제외

    actingAsRole(OrgType::HQ);
    $res = $this->postJson(route('master.safety-stocks.autoSuggest'), ['hospital_id' => $hospital->id, 'months' => 3, 'factor' => 1.0]);
    $res->assertOk();

    $ss = SafetyStock::query()->where('hospital_id', $hospital->id)->where('product_id', $product->id)->first();
    expect($ss->safety_qty)->toBe(10);   // ceil(30/3 * 1.0)
    expect($ss->max_qty)->toBe(30);
    expect($ss->reorder_qty)->toBe(20);
});
