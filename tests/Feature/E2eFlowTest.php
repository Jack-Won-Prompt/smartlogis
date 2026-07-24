<?php

declare(strict_types=1);

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Enums\TxType;
use App\Enums\UsageStatus;
use App\Models\Inbound;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\SafetyStock;
use App\Models\Settlement;
use App\Models\StockTransaction;
use App\Models\UsageReport;
use App\Services\InboundService;
use App\Services\OutboundService;
use App\Services\ReplenishmentService;
use App\Services\StockService;
use App\Services\UsageApprovalService;

/**
 * Phase 7 — E2E 통합 시나리오. CLAUDE.md §11 의 핵심 흐름을 하나의 서사로 관통한다.
 * 공급사 입고 → (안전재고 미달) 자동 보충 → FEFO 피킹·배송 → 병원 입고
 * → 사용분 등록·전송·승인 → 재고 차감·정산 생성 → Lot 이력 전체 추적.
 */
beforeEach(function () {
    $this->stock = app(StockService::class);
    $this->inboundSvc = app(InboundService::class);
    $this->outboundSvc = app(OutboundService::class);
    $this->replenish = app(ReplenishmentService::class);
    $this->approval = app(UsageApprovalService::class);

    $this->supplier = Organization::factory()->supplier()->create();
    $this->warehouse = Organization::factory()->warehouse()->create();
    $this->hospital = Organization::factory()->hospital()->create();
    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'purchase_price' => 1000,
        'sales_price' => 1500,
    ]);

    SafetyStock::create([
        'hospital_id' => $this->hospital->id, 'product_id' => $this->product->id,
        'safety_qty' => 50, 'max_qty' => 200, 'reorder_qty' => 100,
    ]);
});

it('공급사 입고 → 자동보충 → 배송 → 사용분 승인 → 정산 → Lot 추적까지 관통한다', function () {
    // ── 1) 공급사 → 창고 ASN 입고 확정 → 창고 재고 증가 ─────────────────────────
    $inbound = Inbound::factory()->create([
        'direction' => InboundDirection::SUPPLIER_TO_WH,
        'from_org_id' => $this->supplier->id,
        'to_org_id' => $this->warehouse->id,
        'status' => InboundStatus::RECEIVING,
    ]);
    $inbound->items()->create([
        'product_id' => $this->product->id,
        'lot_no' => 'LOT-E2E',
        'expiry_date' => now()->addYear()->toDateString(),
        'qty' => 120,
        'unit_price' => 1000,
    ]);
    $this->inboundSvc->confirm($inbound->fresh('items'));

    $lot = ProductLot::where('product_id', $this->product->id)->where('lot_no', 'LOT-E2E')->firstOrFail();
    expect($this->stock->balance($this->warehouse->id, $this->product->id, $lot->id))->toBe(120);

    // ── 2) 병원 안전재고 미달 → 자동 보충 Outbound(DRAFT) 생성 ──────────────────
    $result = $this->replenish->check($this->hospital->id);
    expect($result['created'])->toBe(1);

    $outbound = Outbound::where('hospital_id', $this->hospital->id)
        ->where('source_type', OutboundSourceType::AUTO_REPLENISH)->firstOrFail();
    expect($outbound->status)->toBe(OutboundStatus::DRAFT);

    // ── 3) FEFO 피킹 → 배송 → 병원 입고 ────────────────────────────────────────
    $this->outboundSvc->pick($outbound->fresh('items'));
    $this->outboundSvc->ship($outbound->fresh());
    $this->outboundSvc->deliver($outbound->fresh(), $this->inboundSvc);

    expect($outbound->fresh()->status)->toBe(OutboundStatus::DELIVERED);
    expect($this->stock->balance($this->warehouse->id, $this->product->id, $lot->id))->toBe(20); // 120 - 100
    expect($this->stock->totalBalance($this->hospital->id, $this->product->id))->toBe(100);

    // ── 4) 병원 사용분 등록 → 전송 → 본사 승인 → 재고 차감 + 정산 생성 ──────────
    $report = UsageReport::factory()->for($this->hospital, 'hospital')->create([
        'status' => UsageStatus::SUBMITTED,
        'submitted_at' => now(),
        'total_amount' => 0,
    ]);
    $report->items()->create([
        'product_id' => $this->product->id,
        'lot_id' => $lot->id,
        'qty' => 30,
        'unit_price' => 1500,
        'amount' => 45000,
    ]);

    $this->approval->approve($report->fresh(), null);

    expect($report->fresh()->status)->toBe(UsageStatus::APPROVED);
    expect($this->stock->totalBalance($this->hospital->id, $this->product->id))->toBe(70); // 100 - 30

    // 매출(병원)·매입(공급사) 정산이 쌍으로 생성됨
    $yearMonth = $report->usage_date->format('Y-m');
    $sales = Settlement::withoutGlobalScopes()->where('year_month', $yearMonth)
        ->where('settle_type', 'SALES')->where('org_id', $this->hospital->id)->first();
    $purchase = Settlement::withoutGlobalScopes()->where('year_month', $yearMonth)
        ->where('settle_type', 'PURCHASE')->where('org_id', $this->supplier->id)->first();
    expect($sales)->not->toBeNull()
        ->and((float) $sales->total_amount)->toBe(45000.0)   // 30 × 1500
        ->and($purchase)->not->toBeNull()
        ->and((float) $purchase->total_amount)->toBe(30000.0); // 30 × 1000

    // ── 5) Lot 추적: 하나의 Lot 에 전 구간 이력이 남는다(리콜 대응) ────────────────
    $txTypes = StockTransaction::where('lot_id', $lot->id)->pluck('tx_type')
        ->map(fn ($t) => $t instanceof TxType ? $t->value : $t)->unique()->values();

    expect($txTypes)->toContain(TxType::IN_SUPPLIER->value)      // 창고 입고
        ->toContain(TxType::OUT_TO_HOSPITAL->value)              // 창고 → 병원 출고
        ->toContain(TxType::IN_HOSPITAL->value)                  // 병원 입고
        ->toContain(TxType::USE->value);                         // 병원 사용
});
