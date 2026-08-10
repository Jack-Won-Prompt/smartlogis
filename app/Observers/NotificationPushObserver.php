<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\NotiType;
use App\Enums\Severity;
use App\Events\NotificationBroadcast;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 알림이 생기면 자동으로 다중 채널(모바일 FCM · 웹 Pusher · 이메일)로 발송한다.
 *
 * 알림 생성 지점이 여러 곳(UsageApprovalService, ReplenishmentService, …)이라
 * 각 지점에서 발송을 호출하게 하면 새 알림을 추가할 때 빠뜨리기 쉽다.
 * 모델 이벤트 한 곳에 묶어 "알림을 만들면 반드시 발송된다" 를 보장한다.
 *
 * created 가 아니라 **커밋 이후**에 보내는 이유: 승인 트랜잭션이 롤백되면
 * 알림도 사라져야 하는데, 발송은 되돌릴 수 없다. 어떤 채널이 실패해도
 * 나머지·업무 트랜잭션에는 영향을 주지 않는다(알림 센터에는 이미 남아 있다).
 */
class NotificationPushObserver
{
    public function __construct(private readonly PushNotifier $push) {}

    /** 트랜잭션 커밋 후에만 실행된다. */
    public bool $afterCommit = true;

    public function created(Notification $notification): void
    {
        // 1) 모바일 — FCM 푸시(기존)
        $this->push->push($notification);

        $userIds = $this->push->recipientIds($notification);
        if ($userIds === []) {
            return;
        }

        // 2) 웹 — Pusher 실시간(대상자 개인 채널)
        try {
            NotificationBroadcast::dispatch($notification, $userIds);
        } catch (\Throwable $e) {
            Log::warning('[noti broadcast] '.$e->getMessage());
        }

        // 3) 이메일 — 중요 알림(위험/공지)만 큐로 발송(과다 발송 방지)
        if ($notification->severity === Severity::CRITICAL || $notification->noti_type === NotiType::NOTICE) {
            try {
                User::query()->whereIn('id', $userIds)->whereNotNull('email')
                    ->pluck('email')
                    ->each(fn (string $to) => Mail::to($to)->queue(new NotificationMail($notification)));
            } catch (\Throwable $e) {
                Log::warning('[noti email] '.$e->getMessage());
            }
        }
    }
}
