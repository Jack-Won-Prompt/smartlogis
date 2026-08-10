<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotiType;
use App\Enums\OutboundStatus;
use App\Enums\Severity;
use App\Models\Notification;
use App\Models\Outbound;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 사용/반납 지연 리마인더 (제안서 예외 프로세스 ⓪).
 * 병원 배송완료(DELIVERED) 후 기준일(기본 30일, config('logistics.usage_close_days'))
 * 경과까지 사용/반납 확정이 없으면 병원(및 라이프)에게 지연 알림을 발송한다.
 * 중복 발송 방지: outbounds.close_reminded_at 로 1회만.
 */
class UsageCloseReminderCommand extends Command
{
    protected $signature = 'usage:close-reminder';

    protected $description = '배송완료 후 N일(기본 30) 무응답 출고 건에 사용/반납 지연 알림을 발송한다';

    public function handle(): int
    {
        $days = (int) config('logistics.usage_close_days', 30);
        $cutoff = Carbon::now()->subDays($days);

        $overdue = Outbound::query()
            ->with('hospital:id,name')
            ->where('status', OutboundStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->whereNull('close_reminded_at')
            ->get();

        $count = 0;
        foreach ($overdue as $ob) {
            $elapsed = (int) $ob->delivered_at->diffInDays(Carbon::now());

            Notification::create([
                'noti_type' => NotiType::USAGE_OVERDUE,
                'severity' => Severity::WARNING,
                'target_role' => null,
                'target_org_id' => $ob->hospital_id,   // 해당 병원(+ HQ·LIFE 는 광역 조회)
                'title' => "사용/반납 지연 — {$ob->outbound_no}",
                'message' => "{$ob->hospital?->name} 배송({$ob->outbound_no}) 후 {$elapsed}일 경과했습니다. 사용분 확정 또는 반납 처리를 진행해 주세요.",
                'link_url' => '/usages/create',
                'is_read' => false,
            ]);

            $ob->update(['close_reminded_at' => Carbon::now()]);
            $count++;
        }

        $this->info("사용/반납 지연 알림 {$count}건 발송(기준 {$days}일).");

        return self::SUCCESS;
    }
}
