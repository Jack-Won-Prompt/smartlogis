<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OutboundStatus;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\ProductLot;
use App\Models\StockTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 재고 변경의 유일한 진입점 (CLAUDE.md §4.1, §8).
 *
 * - 모든 재고 변화는 stock_transactions(원장)에 기록하고, 현재고 캐시 stock_balances 를
 *   같은 DB 트랜잭션 안에서만 갱신한다.
 * - 다른 어디서도 stock_balances 를 직접 수정하지 않는다.
 * - apply()/allocateFefo() 는 호출자가 감싼 트랜잭션 안에서 동작하며 lockForUpdate 로 동시성을 보장한다.
 */
class StockService
{
    /**
     * 재고 트랜잭션 1건을 적용한다. qty 는 부호 포함(입고 +, 출고 -).
     * 결과 현재고가 음수면 예외를 던져 전체 롤백을 유도한다.
     *
     * 반드시 DB::transaction() 안에서 호출한다.
     */
    public function apply(
        TxType $type,
        int $orgId,
        int $productId,
        int $lotId,
        int $qty,
        RefType $refType,
        ?int $refId = null,
        ?float $unitPrice = null,
        ?string $memo = null,
        ?int $createdBy = null,
    ): StockTransaction {
        if ($qty === 0) {
            throw new DomainException('수량이 0인 재고 변경은 처리할 수 없습니다.');
        }

        // 부호 방향이 고정된 유형은 방향 검증
        $dir = $type->signedDirection();
        if ($dir !== null && (($qty > 0 ? 1 : -1) !== $dir)) {
            throw new DomainException("[{$type->label()}] 유형의 수량 부호가 올바르지 않습니다.");
        }

        // 현재고 캐시 잠금
        $current = (int) DB::table('stock_balances')
            ->where('org_id', $orgId)->where('product_id', $productId)->where('lot_id', $lotId)
            ->lockForUpdate()
            ->value('qty');

        $new = $current + $qty;
        if ($new < 0) {
            $lot = ProductLot::with('product')->find($lotId);
            $name = $lot ? $lot->product->product_name : "제품#{$productId}";
            $lotNo = $lot ? $lot->lot_no : "Lot#{$lotId}";
            throw new DomainException(
                "재고가 부족합니다: {$name} (Lot {$lotNo}) — 현재고 {$current}, 요청 ".abs($qty)
            );
        }

        // 원장 기록
        $tx = StockTransaction::create([
            'tx_type' => $type,
            'org_id' => $orgId,
            'product_id' => $productId,
            'lot_id' => $lotId,
            'qty' => $qty,
            'unit_price' => $unitPrice ?? 0,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'memo' => $memo,
            'created_by' => $createdBy,
        ]);

        // 현재고 캐시 갱신(같은 트랜잭션)
        $affected = DB::table('stock_balances')
            ->where('org_id', $orgId)->where('product_id', $productId)->where('lot_id', $lotId)
            ->update(['qty' => $new, 'updated_at' => now()]);

        if ($affected === 0) {
            DB::table('stock_balances')->insert([
                'org_id' => $orgId, 'product_id' => $productId, 'lot_id' => $lotId,
                'qty' => $new, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $tx;
    }

    /**
     * FEFO(유통기한 임박 순) 출고 배정 (CLAUDE.md §7.3).
     * 유통기한 경과 Lot 은 제외한다. 배정만 계산하며 실제 차감은 호출자가 apply() 로 수행한다.
     * lockForUpdate 로 후보 잔고를 잠근다 → 반드시 트랜잭션 안에서 호출.
     *
     * @return array<int, array{lot_id: int, qty: int, expiry_date: string|null}>
     */
    public function allocateFefo(int $orgId, int $productId, int $qty): array
    {
        if ($qty <= 0) {
            throw new DomainException('출고 수량은 1 이상이어야 합니다.');
        }

        $today = Carbon::today()->toDateString();

        $candidates = DB::table('stock_balances as b')
            ->join('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->where('b.org_id', $orgId)
            ->where('b.product_id', $productId)
            ->where('b.qty', '>', 0)
            ->where(function ($q) use ($today) {
                $q->whereNull('l.expiry_date')->orWhereDate('l.expiry_date', '>=', $today);
            })
            ->orderByRaw('CASE WHEN l.expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('l.expiry_date')
            ->orderBy('l.id')
            ->lockForUpdate()
            ->get(['b.lot_id', 'b.qty', 'l.expiry_date']);

        $remaining = $qty;
        $allocation = [];
        foreach ($candidates as $c) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int) $c->qty, $remaining);
            $allocation[] = [
                'lot_id' => (int) $c->lot_id,
                'qty' => $take,
                'expiry_date' => $c->expiry_date,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $available = $qty - $remaining;
            throw new DomainException("출고 가능한 재고가 부족합니다. 요청 {$qty}, 가용 {$available}(유통기한 경과분 제외).");
        }

        return $allocation;
    }

    /** 특정 위치·제품·Lot 현재고. */
    public function balance(int $orgId, int $productId, int $lotId): int
    {
        return (int) DB::table('stock_balances')
            ->where('org_id', $orgId)->where('product_id', $productId)->where('lot_id', $lotId)
            ->value('qty');
    }

    /** 특정 위치·제품 총 현재고(모든 Lot 합). */
    public function totalBalance(int $orgId, int $productId): int
    {
        return (int) DB::table('stock_balances')
            ->where('org_id', $orgId)->where('product_id', $productId)
            ->sum('qty');
    }

    /**
     * 예약 수량 — 승인·피킹 중이지만 아직 출고(SHIPPED)되지 않아 재고에서
     * 빠지지 않은 출고분. 가용재고 계산에 사용한다.
     */
    public function reservedQty(int $orgId, int $productId): int
    {
        return (int) DB::table('outbound_items as oi')
            ->join('outbounds as o', 'o.id', '=', 'oi.outbound_id')
            ->where('o.warehouse_id', $orgId)
            ->where('oi.product_id', $productId)
            ->whereIn('o.status', [OutboundStatus::APPROVED->value, OutboundStatus::PICKING->value])
            ->sum('oi.qty');
    }

    /**
     * 가용재고 = 총 현재고 − 예약(승인·피킹 중 미출고)분. 음수는 0으로 본다.
     * 제안서의 "가용재고(승인분 제외)" 정의를 서버 단일 진입점으로 제공한다.
     */
    public function availableQty(int $orgId, int $productId): int
    {
        return max(0, $this->totalBalance($orgId, $productId) - $this->reservedQty($orgId, $productId));
    }
}
