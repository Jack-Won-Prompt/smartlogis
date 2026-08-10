<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 알림 실시간 브로드캐스트(웹 Pusher) — 수신 대상 사용자 개인 채널로 전송.
 */
class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param  list<int>  $userIds */
    public function __construct(public Notification $notification, public array $userIds) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('user.'.$id), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $n = $this->notification;

        return [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'severity' => $n->severity->value,
            'tone' => $n->severity->tone()->value,
            'noti_label' => $n->noti_type->label(),
            'link_url' => $n->link_url,
        ];
    }
}
