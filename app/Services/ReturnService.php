<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RefType;
use App\Enums\ReturnStatus;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\StockReturn;
use Illuminate\Support\Facades\DB;

/**
 * 반납(병원 → 창고) 처리. 등록 → 배송 → 수령확인(재고 복귀).
 *
 * 재고 변경은 수령확인(receive) 시점에만, StockService 단일 진입점으로 원자 처리한다:
 *   병원 재고 차감(RETURN_HOSPITAL, −) + 창고 재고 복귀(RETURN_TO_WH, +).
 */
class ReturnService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNoService $docNo,
    ) {}

    /**
     * 반납 등록(REQUESTED). 재고 이동은 아직 없음(물품은 병원에 있음).
     *
     * @param  list<array{product_id:int, lot_id:int, qty:int}>  $items
     */
    public function register(int $hospitalId, int $warehouseId, array $items, ?int $by = null): StockReturn
    {
        if ($items === []) {
            throw new DomainException('반납할 품목을 1개 이상 선택하세요.');
        }

        return DB::transaction(function () use ($hospitalId, $warehouseId, $items, $by) {
            $return = StockReturn::create([
                'return_no' => $this->docNo->next('RT'),
                'hospital_id' => $hospitalId,
                'warehouse_id' => $warehouseId,
                'status' => ReturnStatus::REQUESTED,
                'created_by' => $by,
            ]);

            foreach ($items as $it) {
                $qty = (int) $it['qty'];
                if ($qty <= 0) {
                    throw new DomainException('반납 수량은 1 이상이어야 합니다.');
                }
                // 병원 보유 재고를 초과해 반납할 수 없다.
                $onHand = $this->stock->balance($hospitalId, (int) $it['product_id'], (int) $it['lot_id']);
                if ($qty > $onHand) {
                    throw new DomainException("병원 보유 재고({$onHand})를 초과한 반납 수량({$qty})입니다.");
                }
                $return->items()->create([
                    'product_id' => (int) $it['product_id'],
                    'lot_id' => (int) $it['lot_id'],
                    'qty' => $qty,
                ]);
            }

            return $return;
        });
    }

    /** 배송 시작(REQUESTED → SHIPPING). */
    public function ship(StockReturn $return): StockReturn
    {
        if ($return->status !== ReturnStatus::REQUESTED) {
            throw new DomainException('배송으로 넘길 수 없는 상태입니다.', 409);
        }
        $return->update(['status' => ReturnStatus::SHIPPING, 'shipped_at' => now()]);

        return $return;
    }

    /**
     * 수령확인(→ RECEIVED). 병원 재고 차감 + 창고 재고 복귀를 원자적으로 처리.
     * 이미 수령/취소된 반납은 409.
     */
    public function receive(StockReturn $return, ?int $by = null): StockReturn
    {
        if (! in_array($return->status, [ReturnStatus::REQUESTED, ReturnStatus::SHIPPING], true)) {
            throw new DomainException('수령 처리할 수 없는 상태입니다(이미 완료/취소).', 409);
        }

        return DB::transaction(function () use ($return, $by) {
            foreach ($return->items()->get() as $item) {
                // 병원 재고 차감
                $this->stock->apply(
                    type: TxType::RETURN_HOSPITAL,
                    orgId: $return->hospital_id,
                    productId: $item->product_id,
                    lotId: $item->lot_id,
                    qty: -$item->qty,
                    refType: RefType::RETURN,
                    refId: $return->id,
                    createdBy: $by,
                );
                // 창고 재고 복귀
                $this->stock->apply(
                    type: TxType::RETURN_TO_WH,
                    orgId: $return->warehouse_id,
                    productId: $item->product_id,
                    lotId: $item->lot_id,
                    qty: $item->qty,
                    refType: RefType::RETURN,
                    refId: $return->id,
                    createdBy: $by,
                );
            }

            $return->update(['status' => ReturnStatus::RECEIVED, 'received_at' => now()]);

            return $return;
        });
    }

    /** 취소(수령 완료 전에만). */
    public function cancel(StockReturn $return): StockReturn
    {
        if ($return->status === ReturnStatus::RECEIVED) {
            throw new DomainException('이미 수령 완료된 반납은 취소할 수 없습니다.', 409);
        }
        $return->update(['status' => ReturnStatus::CANCELED]);

        return $return;
    }
}
