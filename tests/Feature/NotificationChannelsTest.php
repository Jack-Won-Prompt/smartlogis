<?php

declare(strict_types=1);

use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\Severity;
use App\Events\NotificationBroadcast;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Observers\NotificationPushObserver;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * B-7 다중 채널 알림 — 웹 Pusher(브로드캐스트) + 이메일(중요), FCM(기존).
 * afterCommit 관찰자는 RefreshDatabase 트랜잭션에서 발화하지 않으므로 직접 호출해 검증한다.
 */
function fireObserver(Notification $n): void
{
    (new NotificationPushObserver(app(PushNotifier::class)))->created($n);
}

function makeHospitalUser(): Organization
{
    $hospital = Organization::factory()->hospital()->create();
    User::factory()->create(['org_id' => $hospital->id, 'role' => OrgType::HOSPITAL, 'email' => 'nurse@hosp.test']);

    return $hospital;
}

it('중요(위험) 알림은 웹 Pusher 브로드캐스트 + 이메일을 발송한다', function () {
    Event::fake([NotificationBroadcast::class]);
    Mail::fake();
    $hospital = makeHospitalUser();

    fireObserver(Notification::create([
        'noti_type' => NotiType::EXPIRY, 'severity' => Severity::CRITICAL,
        'target_org_id' => $hospital->id, 'title' => '유통기한 경과', 'message' => '즉시 확인', 'is_read' => false,
    ]));

    Event::assertDispatched(NotificationBroadcast::class);
    Mail::assertSent(NotificationMail::class);
});

it('정보(INFO) 알림은 브로드캐스트만 하고 이메일은 보내지 않는다', function () {
    Event::fake([NotificationBroadcast::class]);
    Mail::fake();
    $hospital = makeHospitalUser();

    fireObserver(Notification::create([
        'noti_type' => NotiType::SAFETY_STOCK, 'severity' => Severity::INFO,
        'target_org_id' => $hospital->id, 'title' => '참고', 'message' => '참고 알림', 'is_read' => false,
    ]));

    Event::assertDispatched(NotificationBroadcast::class);
    Mail::assertNothingSent();
});

it('공지(NOTICE)는 정보 수준이어도 이메일을 발송한다', function () {
    Event::fake([NotificationBroadcast::class]);
    Mail::fake();
    makeHospitalUser();

    fireObserver(Notification::create([
        'noti_type' => NotiType::NOTICE, 'severity' => Severity::INFO,
        'target_role' => null, 'target_org_id' => null, 'title' => '전체 공지', 'message' => '전체 공지 내용', 'is_read' => false,
    ]));

    Mail::assertSent(NotificationMail::class);
});
