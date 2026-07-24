<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotiType;
use App\Enums\Severity;
use App\Models\Notification;
use App\Models\StockBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 유통기한 경고 배치 (CLAUDE.md §7.4). 매일 06:00.
 * 재고가 있는 Lot 을 대상으로 D-90 INFO / D-60 WARNING / D-30·경과 CRITICAL 알림 생성.
 * 같은 Lot·위치의 알림이 당일 이미 있으면 중복 생성하지 않는다.
 */
class ExpiryAlertCommand extends Command
{
    protected $signature = 'expiry:alert';

    protected $description = '유통기한 임박(D-90/60/30) 재고에 대한 알림을 생성한다';

    public function handle(): int
    {
        $today = Carbon::today();
        $limit = $today->copy()->addDays(90)->toDateString();

        $balances = StockBalance::query()
            ->with(['organization', 'product', 'lot'])
            ->where('qty', '>', 0)
            ->whereHas('lot', fn ($q) => $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $limit))
            ->get();

        $count = 0;
        foreach ($balances as $b) {
            $days = (int) $today->diffInDays($b->lot->expiry_date, false);

            [$severity, $label] = match (true) {
                $days < 30 => [Severity::CRITICAL, $days < 0 ? "D+{$days} 경과" : "D-{$days}"],
                $days < 60 => [Severity::WARNING, "D-{$days}"],
                default => [Severity::INFO, "D-{$days}"],
            };

            $message = "{$b->product->product_name} · Lot {$b->lot->lot_no} · {$b->lot->expiry_date->toDateString()} ({$label}) · 재고 {$b->qty}";

            // 당일 중복 방지
            $exists = Notification::query()
                ->where('noti_type', NotiType::EXPIRY)
                ->where('target_org_id', $b->org_id)
                ->where('message', $message)
                ->whereDate('created_at', $today)
                ->exists();
            if ($exists) {
                continue;
            }

            // 위치(창고/병원) + 본사에 알림
            foreach ([$b->org_id, null] as $target) {
                Notification::create([
                    'noti_type' => NotiType::EXPIRY,
                    'severity' => $severity,
                    'target_role' => $target === null ? 'HQ' : null,
                    'target_org_id' => $target,
                    'title' => '유통기한 임박',
                    'message' => $target === null ? "{$b->organization->name} · {$message}" : $message,
                    'link_url' => '/inventory/expiry',
                    'is_read' => false,
                ]);
            }
            $count++;
        }

        $this->info("유통기한 경고 {$count}건 생성");

        return self::SUCCESS;
    }
}
