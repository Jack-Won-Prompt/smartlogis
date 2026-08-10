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
 * B-3 소급·사후 사용 등록 — 라이프사이언스(요청)가 병원을 대신해 등록하고
 * 본사 승인 흐름을 그대로 탄다(과거 사용일 = 소급 허용).
 */
it('라이프사이언스는 병원을 선택해 소급 사용분을 등록한다(대상=선택 병원)', function () {
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();
    $lot = ProductLot::factory()->create(['product_id' => $product->id]);
    DB::transaction(fn () => app(StockService::class)->apply(
        TxType::IN_HOSPITAL, $hospital->id, $product->id, $lot->id, 20, RefType::INBOUND
    ));

    $life = actingAsRole(OrgType::LIFE);

    $res = $this->postJson('/usages', [
        'hospital_id' => $hospital->id,
        'usage_date' => now()->subDays(3)->toDateString(),   // 소급(과거)
        'items' => [['product_id' => $product->id, 'lot_id' => $lot->id, 'qty' => 5]],
    ]);

    $res->assertOk();
    $report = UsageReport::query()->withoutGlobalScopes()->first();
    expect($report)->not->toBeNull();
    expect($report->hospital_id)->toBe($hospital->id);   // 라이프 org 가 아니라 선택한 병원
    expect($report->hospital_id)->not->toBe($life->org_id);
    expect($report->status)->toBe(UsageStatus::DRAFT);
});

it('라이프사이언스가 병원을 선택하지 않으면 등록이 거부된다', function () {
    $product = Product::factory()->create();
    $lot = ProductLot::factory()->create(['product_id' => $product->id]);
    actingAsRole(OrgType::LIFE);

    $this->postJson('/usages', [
        'usage_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'lot_id' => $lot->id, 'qty' => 1]],
    ])->assertStatus(422);
});
