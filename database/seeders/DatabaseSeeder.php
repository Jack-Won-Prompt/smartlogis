<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,   // 조직 4종 + 대표 계정
            ProductSeeder::class,        // 제품 30종 + Lot
            StockSeeder::class,          // 개시 재고(창고/병원) + 안전재고
            NotificationSeeder::class,   // 대표 알림(유통기한/안전재고)
            ChatSeeder::class,           // 영역별 채팅 데모(대화·메시지·파일첨부)
        ]);
    }
}
