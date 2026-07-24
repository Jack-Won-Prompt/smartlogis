<?php

declare(strict_types=1);

use App\Enums\OutboundSourceType;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\SafetyStock;
use App\Services\ReplenishmentService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->stock = app(StockService::class);
    $this->service = app(ReplenishmentService::class);

    $this->supplier = Organization::factory()->supplier()->create();
    $this->warehouse = Organization::factory()->warehouse()->create();
    $this->hospital = Organization::factory()->hospital()->create();
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);

    SafetyStock::create([
        'hospital_id' => $this->hospital->id, 'product_id' => $this->product->id,
        'safety_qty' => 50, 'max_qty' => 150, 'reorder_qty' => 100,
    ]);
});

function whStock(StockService $s, int $orgId, ProductLot $lot, int $qty): void
{
    DB::transaction(fn () => $s->apply(TxType::IN_SUPPLIER, $orgId, $lot->product_id, $lot->id, $qty, RefType::MANUAL));
}

it('안전재고 미달 + 창고 충분 → 자동 보충 출고(초안)를 생성한다', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addYear()->toDateString()]);
    whStock($this->stock, $this->warehouse->id, $lot, 200);      // 창고 충분
    // 병원 현재고는 0 (안전재고 50 미만)

    $result = $this->service->check($this->hospital->id);

    expect($result['created'])->toBe(1);
    $outbound = Outbound::where('hospital_id', $this->hospital->id)->where('source_type', OutboundSourceType::AUTO_REPLENISH)->first();
    expect($outbound)->not->toBeNull();
    expect($outbound->items->first()->qty)->toBe(100); // reorder_qty
});

it('중복 실행해도 미완료 자동보충이 있으면 다시 생성하지 않는다', function () {
    $lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addYear()->toDateString()]);
    whStock($this->stock, $this->warehouse->id, $lot, 200);

    $this->service->check($this->hospital->id);
    $this->service->check($this->hospital->id);

    expect(Outbound::where('source_type', OutboundSourceType::AUTO_REPLENISH)->count())->toBe(1);
});

it('창고 재고 부족 → 공급사 부족 알림을 만들고 출고는 생성하지 않는다', function () {
    // 창고 재고 없음
    $result = $this->service->check($this->hospital->id);

    expect($result['created'])->toBe(0);
    expect($result['shortages'])->toBe(1);
    expect(Outbound::count())->toBe(0);
    expect(Notification::where('target_org_id', $this->supplier->id)->where('title', '납품 요청(재고 부족)')->exists())->toBeTrue();
});
