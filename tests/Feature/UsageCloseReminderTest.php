<?php

declare(strict_types=1);

use App\Enums\NotiType;
use App\Enums\OutboundStatus;
use App\Models\Notification;
use App\Models\Outbound;

/**
 * B-4 사용/반납 지연 리마인더 — 배송완료 후 30일(기준일) 무응답 출고에 알림.
 */
it('배송완료 30일 경과 무응답 출고에 지연 알림을 1회 발송한다', function () {
    // 31일 전 배송완료
    $old = Outbound::factory()->status(OutboundStatus::DELIVERED)->create([
        'delivered_at' => now()->subDays(31),
    ]);
    // 최근(10일) 배송완료 — 대상 아님
    Outbound::factory()->status(OutboundStatus::DELIVERED)->create([
        'delivered_at' => now()->subDays(10),
    ]);

    $this->artisan('usage:close-reminder')->assertSuccessful();

    $notis = Notification::query()->withoutGlobalScopes()
        ->where('noti_type', NotiType::USAGE_OVERDUE)->get();
    expect($notis)->toHaveCount(1);
    expect($notis->first()->target_org_id)->toBe($old->hospital_id);
    expect($old->fresh()->close_reminded_at)->not->toBeNull();

    // 재실행해도 중복 발송하지 않는다(멱등).
    $this->artisan('usage:close-reminder')->assertSuccessful();
    expect(Notification::query()->withoutGlobalScopes()
        ->where('noti_type', NotiType::USAGE_OVERDUE)->count())->toBe(1);
});
