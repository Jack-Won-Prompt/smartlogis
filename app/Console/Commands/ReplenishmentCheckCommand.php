<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReplenishmentService;
use Illuminate\Console\Command;

/**
 * 자동 보충 점검 배치 (CLAUDE.md §7.2). 매일 새벽 실행.
 * 안전재고 미달 품목에 대해 자동 출고(초안) 생성 또는 공급사 부족 알림을 만든다.
 */
class ReplenishmentCheckCommand extends Command
{
    protected $signature = 'replenishment:check';

    protected $description = '안전재고 미달 품목을 점검해 자동 보충을 제안한다';

    public function handle(ReplenishmentService $service): int
    {
        $result = $service->check();

        $this->info("자동 보충 점검 완료 — 자동 출고 {$result['created']}건, 공급사 부족 알림 {$result['shortages']}건");

        return self::SUCCESS;
    }
}
