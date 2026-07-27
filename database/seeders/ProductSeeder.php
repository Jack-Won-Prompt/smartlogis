<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrgType;
use App\Enums\StorageType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 제품 마스터 30종을 공급사 4곳에 분산 생성하고, 제품별 Lot 1~2개를 만든다.
 * 유통기한은 임박(D-20)부터 여유(3년)까지 섞어 대시보드/알림 테스트가 가능하게 한다.
 */
class ProductSeeder extends Seeder
{
    /** @var list<array{string, StorageType, bool}> 대표 품목명 30종 */
    private const CATALOG = [
        ['인공관절 대퇴 스템', StorageType::ROOM, true],
        ['인공관절 경골 트레이', StorageType::ROOM, true],
        ['척추 고정 스크류', StorageType::ROOM, true],
        ['척추 케이지', StorageType::ROOM, true],
        ['관상동맥 스텐트', StorageType::ROOM, true],
        ['말초혈관 스텐트', StorageType::ROOM, true],
        ['심박동기(Pacemaker)', StorageType::ROOM, true],
        ['봉합사 흡수성 3-0', StorageType::ROOM, true],
        ['봉합사 비흡수성 4-0', StorageType::ROOM, true],
        ['수술용 메쉬', StorageType::ROOM, true],
        ['정형용 플레이트', StorageType::ROOM, true],
        ['골시멘트', StorageType::COLD, true],
        ['지혈제(트롬빈)', StorageType::COLD, true],
        ['조직 접착제', StorageType::COLD, true],
        ['인공수정체(IOL)', StorageType::ROOM, true],
        ['백내장 수술 키트', StorageType::ROOM, true],
        ['혈액투석 필터', StorageType::ROOM, false],
        ['중심정맥 카테터', StorageType::ROOM, true],
        ['요관 스텐트', StorageType::ROOM, true],
        ['담도 스텐트', StorageType::ROOM, true],
        ['복강경 트로카', StorageType::ROOM, true],
        ['자동 문합기(Stapler)', StorageType::ROOM, true],
        ['에너지 기반 지혈기 팁', StorageType::ROOM, true],
        ['정형 외고정 장치', StorageType::ROOM, false],
        ['인공 인대', StorageType::FROZEN, true],
        ['동종골 이식재', StorageType::FROZEN, true],
        ['창상 피복재', StorageType::ROOM, false],
        ['드레인 튜브', StorageType::ROOM, false],
        ['인슐린 펌프 세트', StorageType::COLD, false],
        ['혈당 센서', StorageType::COLD, false],
    ];

    public function run(): void
    {
        /** @var list<Organization> $suppliers */
        $suppliers = Organization::query()
            ->where('org_type', OrgType::SUPPLIER)
            ->orderBy('id')
            ->get()
            ->all();

        $supplierCount = count($suppliers);

        $boxOptions = [1, 5, 10];
        $nearDays = [18, 25, 45, 80];

        foreach (self::CATALOG as $i => [$name, $storage, $sterile]) {
            $supplier = $suppliers[$i % $supplierCount];
            $purchase = random_int(20_000, 3_000_000);

            // 멱등: 제품코드 기준 firstOrCreate(재실행 시 중복 삽입 방지)
            $product = Product::firstOrCreate(
                ['product_code' => sprintf('P-%05d', $i + 1)],
                [
                    'product_name' => $name,
                    'udi_di' => $this->digits(14),
                    'gtin' => sprintf('0880601%07d', $i + 1),   // 14자리 고정
                    'edi_code' => $this->digits(8),
                    'spec' => 'MODEL-'.random_int(100, 999),
                    'manufacturer' => $supplier->name,
                    'supplier_id' => $supplier->id,
                    'unit' => 'EA',
                    'box_qty' => $boxOptions[array_rand($boxOptions)],
                    'purchase_price' => $purchase,
                    'sales_price' => (int) round($purchase * (random_int(115, 140) / 100)),
                    'storage_type' => $storage,
                    'is_sterile' => $sterile,
                    'use_lot_control' => true,
                    'use_expiry' => true,
                    'is_active' => true,
                ]
            );

            if (! $product->wasRecentlyCreated) {
                continue; // 이미 존재하는 제품은 Lot 재생성하지 않음
            }

            // Lot 1~2개. 첫 Lot 은 여유, 일부 제품은 임박 Lot 을 하나 더 둔다.
            $lotCount = random_int(1, 2);
            for ($l = 0; $l < $lotCount; $l++) {
                $expiry = $l === 0
                    ? Carbon::today()->addMonths(random_int(8, 36))
                    : Carbon::today()->addDays($nearDays[array_rand($nearDays)]);

                ProductLot::create([
                    'product_id' => $product->id,
                    'lot_no' => sprintf('%s%s%02dK%02d', chr(random_int(65, 90)), chr(random_int(65, 90)), random_int(0, 99), random_int(0, 99)),
                    'expiry_date' => $expiry->format('Y-m-d'),
                ]);
            }
        }
    }

    /** faker 미사용 — n자리 임의 숫자 문자열(운영 --no-dev 실행 가능). */
    private function digits(int $len): string
    {
        $s = '';
        for ($i = 0; $i < $len; $i++) {
            $s .= random_int(0, 9);
        }

        return $s;
    }
}
