<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RefType;
use App\Enums\StocktakeStatus;
use App\Enums\TxType;
use App\Exceptions\DomainException;
use App\Models\Stocktake;
use Illuminate\Support\Facades\DB;

/**
 * 재고 실사 확정 — 각 명세의 diff(실사 - 시스템)만큼 ADJUST 트랜잭션을 생성한다 (CLAUDE.md §4.2).
 */
class StocktakeService
{
    public function __construct(private readonly StockService $stock) {}

    public function confirm(Stocktake $stocktake, ?int $userId = null): Stocktake
    {
        if ($stocktake->status === StocktakeStatus::CONFIRMED) {
            throw DomainException::conflict('이미 확정된 실사입니다.');
        }

        $stocktake->loadMissing('items');

        return DB::transaction(function () use ($stocktake, $userId) {
            foreach ($stocktake->items as $item) {
                $diff = (int) ($item->counted_qty ?? $item->system_qty) - (int) $item->system_qty;
                if ($diff === 0) {
                    continue;
                }

                $this->stock->apply(
                    type: TxType::ADJUST,
                    orgId: $stocktake->org_id,
                    productId: $item->product_id,
                    lotId: $item->lot_id,
                    qty: $diff, // ± 조정
                    refType: RefType::STOCKTAKE,
                    refId: $stocktake->id,
                    memo: "실사 {$stocktake->stocktake_no}".($item->reason ? " · {$item->reason}" : ''),
                    createdBy: $userId,
                );

                $item->update(['diff_qty' => $diff]);
            }

            $stocktake->update(['status' => StocktakeStatus::CONFIRMED, 'confirmed_at' => now()]);

            return $stocktake;
        });
    }
}
