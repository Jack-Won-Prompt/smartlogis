<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InboundStatus;
use App\Enums\RefType;
use App\Exceptions\DomainException;
use App\Models\Inbound;
use App\Models\ProductLot;
use Illuminate\Support\Facades\DB;

/**
 * 입고 확정 — 검수된 입고 명세를 재고에 반영한다.
 * 각 명세의 (제품, Lot번호) 로 ProductLot 을 찾거나 생성하고 StockService::apply(IN) 로 to_org 재고를 늘린다.
 */
class InboundService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * 입고 확정(검수 완료 → CONFIRMED). 멱등성: 이미 확정된 문서는 409.
     */
    public function confirm(Inbound $inbound, ?int $userId = null): Inbound
    {
        if ($inbound->status === InboundStatus::CONFIRMED) {
            throw DomainException::conflict('이미 입고 확정된 문서입니다.');
        }
        if ($inbound->status === InboundStatus::CANCELED) {
            throw new DomainException('취소된 입고는 확정할 수 없습니다.');
        }

        $inbound->loadMissing('items');
        if ($inbound->items->isEmpty()) {
            throw new DomainException('입고 명세가 없습니다.');
        }

        return DB::transaction(function () use ($inbound, $userId) {
            $txType = $inbound->direction->txType();

            foreach ($inbound->items as $item) {
                if ($item->qty <= 0) {
                    throw new DomainException("수량이 올바르지 않습니다: {$item->product_id}");
                }

                $lot = ProductLot::firstOrCreate(
                    ['product_id' => $item->product_id, 'lot_no' => $item->lot_no],
                    ['expiry_date' => $item->expiry_date, 'created_by' => $userId]
                );

                $this->stock->apply(
                    type: $txType,
                    orgId: $inbound->to_org_id,
                    productId: $item->product_id,
                    lotId: $lot->id,
                    qty: $item->qty, // 입고 +
                    refType: RefType::INBOUND,
                    refId: $inbound->id,
                    unitPrice: (float) $item->unit_price,
                    memo: "입고 {$inbound->inbound_no}",
                    createdBy: $userId,
                );
            }

            $inbound->update([
                'status' => InboundStatus::CONFIRMED,
                'confirmed_at' => now(),
            ]);

            return $inbound;
        });
    }
}
