<?php

declare(strict_types=1);

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/**
 * 대화방(conversation.{id}) private 채널 — 해당 대화 참여자만 구독 허용.
 */
Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    return Conversation::where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

/**
 * 사용자 개인 채널(user.{id}) — 본인만. 실시간 알림(웹 Pusher) 수신용.
 */
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
