<?php

declare(strict_types=1);

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\OutboundStatus;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\Inbound;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\ProductLot;
use App\Services\InboundService;
use App\Services\OutboundService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->stock = app(StockService::class);
    $this->inboundSvc = app(InboundService::class);
    $this->outboundSvc = app(OutboundService::class);

    $this->supplier = Organization::factory()->supplier()->create();
    $this->warehouse = Organization::factory()->warehouse()->create();
    $this->hospital = Organization::factory()->hospital()->create();
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
});

it('ASN 입고 확정 시 창고 재고가 증가하고 Lot 이 생성된다', function () {
    $inbound = Inbound::factory()->create([
        'direction' => InboundDirection::SUPPLIER_TO_WH,
        'from_org_id' => $this->supplier->id,
        'to_org_id' => $this->warehouse->id,
        'status' => InboundStatus::RECEIVING,
    ]);
    $inbound->items()->create([
        'product_id' => $this->product->id,
        'lot_no' => 'LOTA1',
        'expiry_date' => now()->addYear()->toDateString(),
        'qty' => 100,
        'unit_price' => 1000,
    ]);

    $this->inboundSvc->confirm($inbound->fresh('items'));

    $lot = ProductLot::where('product_id', $this->product->id)->where('lot_no', 'LOTA1')->first();
    expect($lot)->not->toBeNull();
    expect($this->stock->balance($this->warehouse->id, $this->product->id, $lot->id))->toBe(100);
    expect($inbound->fresh()->status)->toBe(InboundStatus::CONFIRMED);
});

it('입고 확정은 멱등 — 재확정 시 409 도메인 예외', function () {
    $inbound = Inbound::factory()->create([
        'direction' => InboundDirection::SUPPLIER_TO_WH,
        'from_org_id' => $this->supplier->id,
        'to_org_id' => $this->warehouse->id,
        'status' => InboundStatus::CONFIRMED,
    ]);

    expect(fn () => $this->inboundSvc->confirm($inbound))->toThrow(DomainException::class);
});

it('출고 FEFO 피킹 → 배송완료로 창고는 차감·병원은 증가한다', function () {
    // 창고에 두 Lot 입고(임박/여유)
    $near = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(20)->toDateString()]);
    $far = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(300)->toDateString()]);
    DB::transaction(function () use ($near, $far) {
        $this->stock->apply(TxType::IN_SUPPLIER, $this->warehouse->id, $this->product->id, $near->id, 30, RefType::MANUAL);
        $this->stock->apply(TxType::IN_SUPPLIER, $this->warehouse->id, $this->product->id, $far->id, 100, RefType::MANUAL);
    });

    $outbound = Outbound::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'hospital_id' => $this->hospital->id,
        'status' => OutboundStatus::APPROVED,
    ]);
    $outbound->items()->create(['product_id' => $this->product->id, 'qty' => 50, 'unit_price' => 1500]);

    // 피킹(FEFO: 임박 30 + 여유 20)
    $this->outboundSvc->pick($outbound->fresh('items'));

    expect($this->stock->balance($this->warehouse->id, $this->product->id, $near->id))->toBe(0);
    expect($this->stock->balance($this->warehouse->id, $this->product->id, $far->id))->toBe(80);
    expect($outbound->fresh()->status)->toBe(OutboundStatus::PICKING);

    // 배송 → 완료
    $this->outboundSvc->ship($outbound->fresh());
    $this->outboundSvc->deliver($outbound->fresh(), $this->inboundSvc);

    // 병원 재고 = 50 (두 Lot 합)
    expect($this->stock->totalBalance($this->hospital->id, $this->product->id))->toBe(50);
    expect($outbound->fresh()->status)->toBe(OutboundStatus::DELIVERED);
});

it('재고 부족 시 피킹은 예외로 막힌다', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(100)->toDateString()]);
    DB::transaction(fn () => $this->stock->apply(TxType::IN_SUPPLIER, $this->warehouse->id, $this->product->id, $lot->id, 10, RefType::MANUAL));

    $outbound = Outbound::factory()->create(['warehouse_id' => $this->warehouse->id, 'hospital_id' => $this->hospital->id, 'status' => OutboundStatus::APPROVED]);
    $outbound->items()->create(['product_id' => $this->product->id, 'qty' => 50, 'unit_price' => 1000]);

    expect(fn () => $this->outboundSvc->pick($outbound->fresh('items')))->toThrow(DomainException::class);
    // 롤백되어 재고 불변
    expect($this->stock->balance($this->warehouse->id, $this->product->id, $lot->id))->toBe(10);
});
