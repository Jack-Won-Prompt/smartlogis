<?php

declare(strict_types=1);

use App\Enums\RefType;
use App\Enums\ReturnStatus;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\StockReturn;
use App\Services\ReturnService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->stock = app(StockService::class);
    $this->service = app(ReturnService::class);
    $this->wh = Organization::factory()->warehouse()->create();
    $this->hospital = Organization::factory()->hospital()->create();
    $this->product = Product::factory()->create();
    $this->lot = ProductLot::factory()->create(['product_id' => $this->product->id]);

    // 병원에 반납할 재고 40 개를 만든다.
    DB::transaction(fn () => $this->stock->apply(
        TxType::IN_HOSPITAL, $this->hospital->id, $this->product->id, $this->lot->id, 40, RefType::INBOUND
    ));
});

it('반납 등록은 재고를 옮기지 않고 REQUESTED 로 남는다', function () {
    $return = $this->service->register($this->hospital->id, $this->wh->id, [
        ['product_id' => $this->product->id, 'lot_id' => $this->lot->id, 'qty' => 10],
    ]);

    expect($return->status)->toBe(ReturnStatus::REQUESTED);
    expect($return->return_no)->toStartWith('RT-');
    // 아직 재고 이동 없음
    expect($this->stock->balance($this->hospital->id, $this->product->id, $this->lot->id))->toBe(40);
    expect($this->stock->balance($this->wh->id, $this->product->id, $this->lot->id))->toBe(0);
});

it('수령확인 시 병원 재고 차감 + 창고 재고 복귀가 원자적으로 처리된다', function () {
    $return = $this->service->register($this->hospital->id, $this->wh->id, [
        ['product_id' => $this->product->id, 'lot_id' => $this->lot->id, 'qty' => 15],
    ]);

    $this->service->receive($return->fresh());

    expect($return->fresh()->status)->toBe(ReturnStatus::RECEIVED);
    expect($this->stock->balance($this->hospital->id, $this->product->id, $this->lot->id))->toBe(25);
    expect($this->stock->balance($this->wh->id, $this->product->id, $this->lot->id))->toBe(15);
});

it('병원 보유 재고를 초과한 반납 등록은 막는다', function () {
    expect(fn () => $this->service->register($this->hospital->id, $this->wh->id, [
        ['product_id' => $this->product->id, 'lot_id' => $this->lot->id, 'qty' => 50],
    ]))->toThrow(DomainException::class);

    // 롤백되어 반납 레코드도 생기지 않는다
    expect(StockReturn::count())->toBe(0);
});

it('이미 수령 완료된 반납은 다시 수령할 수 없다(멱등성)', function () {
    $return = $this->service->register($this->hospital->id, $this->wh->id, [
        ['product_id' => $this->product->id, 'lot_id' => $this->lot->id, 'qty' => 5],
    ]);
    $this->service->receive($return->fresh());

    expect(fn () => $this->service->receive($return->fresh()))->toThrow(DomainException::class);
    // 재고는 한 번만 반영
    expect($this->stock->balance($this->wh->id, $this->product->id, $this->lot->id))->toBe(5);
});
