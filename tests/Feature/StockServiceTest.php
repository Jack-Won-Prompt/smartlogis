<?php

declare(strict_types=1);

use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\StockTransaction;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->service = app(StockService::class);
    $this->wh = Organization::factory()->warehouse()->create();
    $this->product = Product::factory()->create();
});

function openingStock(StockService $s, int $orgId, ProductLot $lot, int $qty): void
{
    DB::transaction(fn () => $s->apply(
        TxType::IN_SUPPLIER, $orgId, $lot->product_id, $lot->id, $qty, RefType::MANUAL
    ));
}

it('입고 적용 시 현재고와 원장이 함께 증가한다', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id]);

    openingStock($this->service, $this->wh->id, $lot, 100);

    expect($this->service->balance($this->wh->id, $this->product->id, $lot->id))->toBe(100);
    expect(StockTransaction::where('lot_id', $lot->id)->count())->toBe(1);
});

it('재고보다 많은 출고는 예외로 막고 롤백한다', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id]);
    openingStock($this->service, $this->wh->id, $lot, 30);

    expect(fn () => DB::transaction(fn () => $this->service->apply(
        TxType::USE, $this->wh->id, $this->product->id, $lot->id, -50, RefType::USAGE
    )))->toThrow(DomainException::class);

    // 롤백되어 현재고 불변
    expect($this->service->balance($this->wh->id, $this->product->id, $lot->id))->toBe(30);
    expect(StockTransaction::where('lot_id', $lot->id)->where('tx_type', TxType::USE)->count())->toBe(0);
});

it('고정 부호 유형에 반대 부호를 주면 예외', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id]);

    expect(fn () => DB::transaction(fn () => $this->service->apply(
        TxType::IN_SUPPLIER, $this->wh->id, $this->product->id, $lot->id, -10, RefType::MANUAL
    )))->toThrow(DomainException::class);
});

it('FEFO 는 유통기한 임박 Lot 부터 배정하고 경과분은 제외한다', function () {
    $near = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(20)->toDateString()]);
    $far = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(300)->toDateString()]);
    $expired = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->subDay()->toDateString()]);

    openingStock($this->service, $this->wh->id, $near, 40);
    openingStock($this->service, $this->wh->id, $far, 100);
    openingStock($this->service, $this->wh->id, $expired, 100);

    $alloc = DB::transaction(fn () => $this->service->allocateFefo($this->wh->id, $this->product->id, 60));

    // 임박 40 + 여유 20, 경과 Lot 은 사용 안 함
    expect($alloc)->toHaveCount(2);
    expect($alloc[0]['lot_id'])->toBe($near->id);
    expect($alloc[0]['qty'])->toBe(40);
    expect($alloc[1]['lot_id'])->toBe($far->id);
    expect($alloc[1]['qty'])->toBe(20);
    expect(collect($alloc)->pluck('lot_id'))->not->toContain($expired->id);
});

it('가용 재고보다 많은 FEFO 요청은 예외', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(100)->toDateString()]);
    openingStock($this->service, $this->wh->id, $lot, 10);

    expect(fn () => DB::transaction(fn () => $this->service->allocateFefo($this->wh->id, $this->product->id, 50)))
        ->toThrow(DomainException::class);
});

it('가용재고 = 총 현재고 − 승인·피킹 중 예약분', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addDays(100)->toDateString()]);
    openingStock($this->service, $this->wh->id, $lot, 100);

    $hospital = Organization::factory()->hospital()->create();

    // 승인 상태 출고 30 → 예약 30
    $ob = Outbound::create([
        'outbound_no' => 'OB-TEST-0001', 'warehouse_id' => $this->wh->id, 'hospital_id' => $hospital->id,
        'status' => OutboundStatus::APPROVED->value, 'source_type' => OutboundSourceType::MANUAL->value,
    ]);
    OutboundItem::create(['outbound_id' => $ob->id, 'product_id' => $this->product->id, 'lot_id' => $lot->id, 'qty' => 30]);

    expect($this->service->totalBalance($this->wh->id, $this->product->id))->toBe(100);
    expect($this->service->reservedQty($this->wh->id, $this->product->id))->toBe(30);
    expect($this->service->availableQty($this->wh->id, $this->product->id))->toBe(70);

    // 출고 완료(SHIPPED)는 이미 재고에서 빠지므로 예약에서 제외
    $ob->update(['status' => OutboundStatus::SHIPPED->value]);
    expect($this->service->reservedQty($this->wh->id, $this->product->id))->toBe(0);
    expect($this->service->availableQty($this->wh->id, $this->product->id))->toBe(100);
});
