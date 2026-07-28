<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Notification;
use App\Services\PushNotifier;

/**
 * 알림이 생기면 자동으로 푸시를 보낸다.
 *
 * 알림 생성 지점이 여러 곳(UsageApprovalService, ReplenishmentService, …)이라
 * 각 지점에서 발송을 호출하게 하면 새 알림을 추가할 때 빠뜨리기 쉽다.
 * 모델 이벤트 한 곳에 묶어 "알림을 만들면 반드시 푸시가 나간다" 를 보장한다.
 *
 * created 가 아니라 **커밋 이후**에 보내는 이유: 승인 트랜잭션이 롤백되면
 * 알림도 사라져야 하는데, 푸시는 되돌릴 수 없다.
 */
class NotificationPushObserver
{
    public function __construct(private readonly PushNotifier $push) {}

    /** 트랜잭션 커밋 후에만 실행된다. */
    public bool $afterCommit = true;

    public function created(Notification $notification): void
    {
        $this->push->push($notification);
    }
}
