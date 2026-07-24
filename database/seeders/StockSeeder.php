<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrgType;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\SafetyStock;
use App\Models\StockTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 초기(개시) 재고를 배치한다.
 *
 * 개시 재고도 재고 원장(stock_transactions)을 통해서만 만든다(CLAUDE.md §4.1-1).
 * StockService 는 Phase 4 에 도입되므로, 여기서는 원장 INSERT + 캐시 갱신을
 * 한 트랜잭션으로 묶는 openingBalance() 헬퍼로 규칙을 지킨다.
 *
 * 배치 시나리오:
 *  - 창고: 모든 Lot 을 넉넉히 입고
 *  - 병원: 일부 품목만 선납. 안전재고 대비 "충분/근접/미달"이 골고루 섞이도록 구성
 */
class StockSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Organization::query()->where('org_type', OrgType::WAREHOUSE)->firstOrFail();

        /** @var list<Organization> $hospitals */
        $hospitals = Organization::query()
            ->where('org_type', OrgType::HOSPITAL)
            ->orderBy('id')
            ->get()
            ->all();

        /** @var list<ProductLot> $lots */
        $lots = ProductLot::query()->with('product')->get()->all();

        // 1) 창고: 모든 Lot 입고
        foreach ($lots as $lot) {
            $this->openingBalance(
                TxType::IN_SUPPLIER,
                $warehouse->id,
                $lot,
                fake()->numberBetween(200, 600),
                (float) $lot->product->purchase_price,
            );
        }

        // 2) 병원 선납 + 안전재고 설정
        $products = Product::query()->orderBy('id')->get();

        foreach ($hospitals as $hIndex => $hospital) {
            // 병원마다 제품의 약 60% 를 취급
            $handled = $products->random((int) ceil($products->count() * 0.6));

            foreach ($handled as $product) {
                $safety = fake()->numberBetween(20, 80);

                SafetyStock::create([
                    'hospital_id' => $hospital->id,
                    'product_id' => $product->id,
                    'safety_qty' => $safety,
                    'max_qty' => $safety * 3,
                    'reorder_qty' => $safety * 2,
                ]);

                // 현재고를 안전재고 대비 충분/근접/미달로 분배
                $ratio = fake()->randomElement([1.6, 1.2, 0.9, 0.5, 0.0]);
                $onHand = (int) round($safety * $ratio);

                if ($onHand <= 0) {
                    continue; // 미달(0) — 자동보충 대상. 잔고 행을 만들지 않는다.
                }

                // 병원 선납은 제품의 첫 Lot 으로 배치
                $lot = $product->lots()->orderBy('id')->first();
                if ($lot === null) {
                    continue;
                }

                $this->openingBalance(
                    TxType::IN_HOSPITAL,
                    $hospital->id,
                    $lot,
                    $onHand,
                    (float) $product->sales_price,
                );
            }
        }
    }

    /**
     * 개시 재고 1건: 원장 기록 + 현재고 캐시 증가를 한 트랜잭션으로.
     */
    private function openingBalance(TxType $type, int $orgId, ProductLot $lot, int $qty, float $unitPrice): void
    {
        DB::transaction(function () use ($type, $orgId, $lot, $qty, $unitPrice) {
            StockTransaction::create([
                'tx_type' => $type,
                'org_id' => $orgId,
                'product_id' => $lot->product_id,
                'lot_id' => $lot->id,
                'qty' => $qty,                 // 개시 입고는 항상 +
                'unit_price' => $unitPrice,
                'ref_type' => RefType::MANUAL,
                'ref_id' => null,
                'memo' => '개시 재고',
            ]);

            // 복합 PK 캐시는 원자적 upsert 로 갱신(개시 재고이므로 항상 증가).
            $affected = DB::table('stock_balances')
                ->where('org_id', $orgId)
                ->where('product_id', $lot->product_id)
                ->where('lot_id', $lot->id)
                ->increment('qty', $qty, ['updated_at' => now()]);

            if ($affected === 0) {
                DB::table('stock_balances')->insert([
                    'org_id' => $orgId,
                    'product_id' => $lot->product_id,
                    'lot_id' => $lot->id,
                    'qty' => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
