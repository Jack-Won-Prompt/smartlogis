<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\OutboundStatus;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\Inbound;
use App\Models\Outbound;
use Illuminate\Support\Facades\DB;

/**
 * 출고 흐름 — FEFO 피킹(창고 재고 차감) → 배송 → 병원 입고(병원 재고 증가).
 */
class OutboundService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNoService $docNo,
    ) {}

    /**
     * FEFO 피킹 — 각 명세 수량을 창고 재고에서 유통기한 임박 순으로 차감하고 lot_id 를 배정한다.
     * 한 명세가 여러 Lot 에 걸치면 명세를 Lot 별로 분할한다.
     */
    /**
     * @param  array<int, int|string>|null  $itemIds  지정 시 해당 명세만 피킹(부분 피킹). null 이면 전체.
     */
    public function pick(Outbound $outbound, ?int $userId = null, ?array $itemIds = null): Outbound
    {
        if (! in_array($outbound->status, [OutboundStatus::APPROVED, OutboundStatus::DRAFT, OutboundStatus::PICKING], true)) {
            throw DomainException::conflict('피킹할 수 없는 상태입니다: '.$outbound->status->label());
        }

        $outbound->loadMissing('items');
        if ($outbound->items->isEmpty()) {
            throw new DomainException('출고 명세가 없습니다.');
        }

        $ids = $itemIds !== null ? array_map('intval', $itemIds) : null;

        return DB::transaction(function () use ($outbound, $userId, $ids) {
            $picked = 0;
            foreach ($outbound->items as $item) {
                if ($item->lot_id !== null) {
                    continue; // 이미 배정됨
                }
                if ($ids !== null && ! in_array((int) $item->id, $ids, true)) {
                    continue; // 선택되지 않은 품목은 건너뜀(부분 피킹)
                }

                $allocation = $this->stock->allocateFefo($outbound->warehouse_id, $item->product_id, $item->qty);

                // 첫 Lot 은 현재 명세에 배정, 나머지는 명세 복제
                foreach ($allocation as $i => $alloc) {
                    $this->stock->apply(
                        type: TxType::OUT_TO_HOSPITAL,
                        orgId: $outbound->warehouse_id,
                        productId: $item->product_id,
                        lotId: $alloc['lot_id'],
                        qty: -$alloc['qty'], // 출고 -
                        refType: RefType::OUTBOUND,
                        refId: $outbound->id,
                        unitPrice: (float) $item->unit_price,
                        memo: "출고 {$outbound->outbound_no}",
                        createdBy: $userId,
                    );

                    if ($i === 0) {
                        $item->update(['lot_id' => $alloc['lot_id'], 'qty' => $alloc['qty']]);
                    } else {
                        $outbound->items()->create([
                            'product_id' => $item->product_id,
                            'lot_id' => $alloc['lot_id'],
                            'qty' => $alloc['qty'],
                            'unit_price' => $item->unit_price,
                        ]);
                    }
                }
                $picked++;
            }

            if ($picked === 0) {
                throw DomainException::conflict('피킹할 품목이 없습니다(이미 피킹되었거나 선택되지 않았습니다).');
            }

            $outbound->update(['status' => OutboundStatus::PICKING]);

            return $outbound;
        });
    }

    /** 배송 시작(SHIPPED). 모든 품목이 피킹(lot 배정)된 뒤에만 가능. */
    public function ship(Outbound $outbound): Outbound
    {
        if ($outbound->status !== OutboundStatus::PICKING) {
            throw DomainException::conflict('피킹 완료 후에만 배송할 수 있습니다.');
        }
        if ($outbound->items()->whereNull('lot_id')->exists()) {
            throw DomainException::conflict('아직 피킹되지 않은 품목이 있습니다. 전체 피킹 후 배송하세요.');
        }
        $outbound->update(['status' => OutboundStatus::SHIPPED, 'shipped_at' => now()]);

        return $outbound;
    }

    /**
     * 배송 완료 → 병원 입고(WH_TO_HOSPITAL) 생성·확정으로 병원 재고를 늘린다.
     */
    public function deliver(Outbound $outbound, InboundService $inboundService, ?int $userId = null): Outbound
    {
        if ($outbound->status !== OutboundStatus::SHIPPED) {
            throw DomainException::conflict('배송중 상태에서만 배송 완료할 수 있습니다.');
        }

        return DB::transaction(function () use ($outbound, $inboundService, $userId) {
            $outbound->loadMissing('items.lot');

            // 병원 입고 문서 생성
            $inbound = Inbound::create([
                'inbound_no' => $this->docNo->next('IB'),
                'direction' => InboundDirection::WH_TO_HOSPITAL,
                'from_org_id' => $outbound->warehouse_id,
                'to_org_id' => $outbound->hospital_id,
                'status' => InboundStatus::RECEIVING,
                'planned_date' => now()->toDateString(),
                'outbound_id' => $outbound->id,
                'created_by' => $userId,
            ]);

            foreach ($outbound->items as $item) {
                if ($item->lot_id === null) {
                    continue;
                }
                $inbound->items()->create([
                    'product_id' => $item->product_id,
                    'lot_no' => $item->lot->lot_no,
                    'expiry_date' => $item->lot->expiry_date,
                    'qty' => $item->qty,
                    'unit_price' => $item->unit_price,
                ]);
            }

            $inboundService->confirm($inbound, $userId);

            $outbound->update(['status' => OutboundStatus::DELIVERED, 'delivered_at' => now()]);

            return $outbound;
        });
    }
}
