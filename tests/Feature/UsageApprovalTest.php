<?php

declare(strict_types=1);

use App\Enums\RefType;
use App\Enums\SettleType;
use App\Enums\TxType;
use App\Enums\UsageStatus;
use App\Exceptions\DomainException;
use App\Models\MonthlyClosing;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Settlement;
use App\Models\UsageReport;
use App\Services\StockService;
use App\Services\UsageApprovalService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->stock = app(StockService::class);
    $this->service = app(UsageApprovalService::class);

    $this->supplier = Organization::factory()->supplier()->create();
    $this->hospital = Organization::factory()->hospital()->create();
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'purchase_price' => 1000, 'sales_price' => 1500]);
    $this->lot = ProductLot::factory()->create(['product_id' => $this->product->id, 'expiry_date' => now()->addYear()->toDateString()]);
});

function hospitalStock(StockService $s, int $hospitalId, ProductLot $lot, int $qty): void
{
    DB::transaction(fn () => $s->apply(TxType::IN_HOSPITAL, $hospitalId, $lot->product_id, $lot->id, $qty, RefType::INBOUND));
}

function submittedReport($hospital, $product, $lot, int $qty, float $unit = 1500): UsageReport
{
    $report = UsageReport::factory()->submitted()->for($hospital, 'hospital')->create(['usage_date' => now()->toDateString()]);
    $report->items()->create([
        'product_id' => $product->id, 'lot_id' => $lot->id,
        'qty' => $qty, 'unit_price' => $unit, 'amount' => $unit * $qty,
    ]);

    return $report->load('items');
}

it('사용분 승인 시 병원 재고가 차감되고 매출/매입 정산이 생성된다', function () {
    hospitalStock($this->stock, $this->hospital->id, $this->lot, 100);
    $report = submittedReport($this->hospital, $this->product, $this->lot, 30);

    $this->service->approve($report);

    expect($this->stock->balance($this->hospital->id, $this->product->id, $this->lot->id))->toBe(70);
    expect($report->fresh()->status)->toBe(UsageStatus::APPROVED);

    // 매출(병원): 30 × 1500 = 45000
    $sales = Settlement::where('org_id', $this->hospital->id)->where('settle_type', SettleType::SALES)->first();
    expect((float) $sales->total_amount)->toBe(45000.0);
    // 매입(공급사): 30 × 1000 = 30000
    $purchase = Settlement::where('org_id', $this->supplier->id)->where('settle_type', SettleType::PURCHASE)->first();
    expect((float) $purchase->total_amount)->toBe(30000.0);
});

it('재고를 초과하는 사용분 승인은 롤백되고 재고·상태가 불변', function () {
    hospitalStock($this->stock, $this->hospital->id, $this->lot, 20);
    $report = submittedReport($this->hospital, $this->product, $this->lot, 50);

    expect(fn () => $this->service->approve($report))->toThrow(DomainException::class);

    expect($this->stock->balance($this->hospital->id, $this->product->id, $this->lot->id))->toBe(20);
    expect($report->fresh()->status)->toBe(UsageStatus::SUBMITTED);
    expect(Settlement::count())->toBe(0);
});

it('이미 승인된 사용분 재승인은 409', function () {
    hospitalStock($this->stock, $this->hospital->id, $this->lot, 100);
    $report = submittedReport($this->hospital, $this->product, $this->lot, 10);
    $this->service->approve($report);

    expect(fn () => $this->service->approve($report->fresh()->load('items')))->toThrow(DomainException::class);
});

it('마감된 월의 사용분은 승인이 차단된다(403)', function () {
    hospitalStock($this->stock, $this->hospital->id, $this->lot, 100);
    $report = submittedReport($this->hospital, $this->product, $this->lot, 10);
    MonthlyClosing::create(['year_month' => now()->format('Y-m'), 'closed_at' => now()]);

    try {
        $this->service->approve($report);
        expect(false)->toBeTrue('예외가 발생해야 함');
    } catch (DomainException $e) {
        expect($e->status())->toBe(403);
    }

    expect($report->fresh()->status)->toBe(UsageStatus::SUBMITTED);
});

it('사용분 반려는 상태를 REJECTED 로 바꾸고 재고는 건드리지 않는다', function () {
    hospitalStock($this->stock, $this->hospital->id, $this->lot, 100);
    $report = submittedReport($this->hospital, $this->product, $this->lot, 10);

    $this->service->reject($report, '수량 오류');

    expect($report->fresh()->status)->toBe(UsageStatus::REJECTED);
    expect($report->fresh()->reject_reason)->toBe('수량 오류');
    expect($this->stock->balance($this->hospital->id, $this->product->id, $this->lot->id))->toBe(100);
});
