<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 알림 레코드 → 대상자의 기기 → 푸시 발송.
 *
 * 대상 판별은 Notification::scopeVisibleTo 와 **정확히 같은 규칙**을 뒤집어 쓴다.
 * 규칙이 두 벌이 되면 "알림 센터에는 있는데 푸시는 안 온다" 같은 어긋남이 생긴다.
 *   - target_org_id 가 있으면 그 조직 사용자
 *   - 없고 target_role 이 있으면 그 역할 사용자
 *   - 본사(HQ)는 모든 알림을 본다
 */
class PushNotifier
{
    public function __construct(private readonly FcmSender $fcm) {}

    /**
     * 알림 하나를 대상자 전원의 기기로 보낸다.
     *
     * 실패해도 예외를 던지지 않는다 — 푸시가 안 갔다고 승인·입고 같은 업무
     * 트랜잭션을 되돌릴 이유는 없다. 알림 센터에는 이미 남아 있다.
     */
    public function push(Notification $notification): void
    {
        if (! $this->fcm->enabled()) {
            return;
        }

        try {
            $userIds = $this->recipientIds($notification);

            if ($userIds === []) {
                return;
            }

            $tokens = ApiToken::query()
                ->whereIn('user_id', $userIds)
                ->whereNotNull('push_token')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->limit((int) config('fcm.sync_limit'))
                ->pluck('push_token')
                ->all();

            if ($tokens === []) {
                return;
            }

            $result = $this->fcm->send(
                $tokens,
                $notification->title,
                $notification->message ?? '',
                [
                    // 앱이 알림을 탭했을 때 어느 화면으로 갈지.
                    'link_url' => $notification->link_url ?? '',
                    'noti_type' => $notification->noti_type->value,
                    'severity' => $notification->severity->value,
                    'notification_id' => (string) $notification->id,
                ],
            );

            // 죽은 토큰은 지운다 — 두면 발송할 때마다 실패가 쌓인다.
            if ($result['invalid'] !== []) {
                ApiToken::query()
                    ->whereIn('push_token', $result['invalid'])
                    ->update(['push_token' => null]);
            }
        } catch (\Throwable $e) {
            Log::error('푸시 발송 실패(무시하고 계속): '.$e->getMessage(), [
                'notification_id' => $notification->id,
            ]);
        }
    }

    /**
     * 이 알림을 받아야 할 사용자 ID.
     *
     * @return array<int, int>
     */
    public function recipientIds(Notification $notification): array
    {
        $query = User::query()->whereNotNull('org_id');

        if ($notification->target_org_id !== null) {
            // 특정 조직 대상 + 본사(전체를 보는 역할)
            $query->where(fn ($q) => $q
                ->where('org_id', $notification->target_org_id)
                ->orWhereHas('organization', fn ($o) => $o->where('org_type', 'HQ')));
        } elseif ($notification->target_role !== null) {
            $query->whereHas('organization', fn ($o) => $o
                ->whereIn('org_type', [$notification->target_role->value, 'HQ']));
        }
        // else: 대상 조직·역할이 모두 없으면 전체 공지 — 전 사용자에게 발송.
        // (시스템 알림은 항상 조직 또는 역할을 지정하므로 이 분기로 오지 않는다.
        //  관리자 공지(NOTICE)의 '전체' 대상만 여기에 해당한다.)

        return $query->pluck('id')->all();
    }
}
