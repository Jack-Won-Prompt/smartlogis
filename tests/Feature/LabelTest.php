<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\OutboundStatus;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\Product;
use App\Models\ProductLot;
use App\Services\LabelService;

it('GS1 문자열을 AI 구조(01 GTIN / 17 유통기한 / 10 Lot)로 만든다', function () {
    $svc = app(LabelService::class);
    expect($svc->gs1('08806010000001', '2026-08-10', 'LOT123'))
        ->toBe('01088060100000011726081010LOT123');
});

it('QR 코드 data URI(PNG)를 생성한다', function () {
    expect(app(LabelService::class)->qrDataUri('01088060100000011726081010LOT123'))
        ->toStartWith('data:image/png;base64,');
});

it('출고 라벨 페이지는 배정된 LOT 항목에 QR 라벨을 렌더한다', function () {
    $wh = Organization::factory()->warehouse()->create();
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create(['gtin' => '08806010000009']);
    $lot = ProductLot::factory()->create(['product_id' => $product->id, 'lot_no' => 'A23K01', 'expiry_date' => '2026-12-31']);

    $ob = Outbound::factory()->status(OutboundStatus::DELIVERED)->create([
        'warehouse_id' => $wh->id, 'hospital_id' => $hospital->id,
    ]);
    OutboundItem::create(['outbound_id' => $ob->id, 'product_id' => $product->id, 'lot_id' => $lot->id, 'qty' => 3]);

    actingAsRole(OrgType::HQ);
    $res = $this->get("/outbounds/{$ob->id}/labels");
    $res->assertOk();
    $res->assertSee('A23K01');
    $res->assertSee('data:image/png;base64,', false);
});
