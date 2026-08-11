<?php

declare(strict_types=1);

use App\Enums\InboundStatus;
use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\Severity;
use App\Livewire\NotificationCenter;
use App\Models\Inbound;
use App\Models\Notification;
use Livewire\Livewire;

// ── 입고 삭제 ─────────────────────────────────────────────
it('확정 전(PLANNED) 입고는 삭제할 수 있다', function () {
    $inbound = Inbound::factory()->status(InboundStatus::PLANNED)->create();
    actingAsRole(OrgType::WAREHOUSE);

    $this->deleteJson("/inbounds/{$inbound->id}")->assertOk();
    expect(Inbound::query()->withoutGlobalScopes()->find($inbound->id))->toBeNull();
});

it('확정된(CONFIRMED) 입고는 삭제할 수 없다(재고 무결성)', function () {
    $inbound = Inbound::factory()->status(InboundStatus::CONFIRMED)->create();
    actingAsRole(OrgType::HQ);

    $this->deleteJson("/inbounds/{$inbound->id}")->assertStatus(409);
    expect(Inbound::query()->withoutGlobalScopes()->find($inbound->id))->not->toBeNull();
});

// ── 알림 삭제 ─────────────────────────────────────────────
function makeNoti(): Notification
{
    return Notification::create([
        'noti_type' => NotiType::NOTICE, 'severity' => Severity::INFO,
        'target_role' => null, 'target_org_id' => null,
        'title' => '테스트 알림', 'message' => '내용', 'is_read' => true,
    ]);
}

it('본사(HQ)는 알림을 삭제할 수 있다', function () {
    $n = makeNoti();
    actingAsRole(OrgType::HQ);

    Livewire::test(NotificationCenter::class)->call('delete', $n->id);
    expect(Notification::find($n->id))->toBeNull();
});

it('본사가 아니면 알림을 삭제할 수 없다', function () {
    $n = makeNoti();
    actingAsRole(OrgType::HOSPITAL);

    Livewire::test(NotificationCenter::class)->call('delete', $n->id);
    expect(Notification::find($n->id))->not->toBeNull();
});

it('본사는 읽은 알림을 일괄 삭제한다', function () {
    makeNoti();
    makeNoti();
    Notification::create([  // 안읽음 — 삭제 대상 아님
        'noti_type' => NotiType::NOTICE, 'severity' => Severity::INFO,
        'title' => '안읽음', 'message' => 'x', 'is_read' => false,
    ]);
    actingAsRole(OrgType::HQ);

    Livewire::test(NotificationCenter::class)->call('deleteRead');
    expect(Notification::where('is_read', true)->count())->toBe(0);
    expect(Notification::where('is_read', false)->count())->toBe(1);
});
