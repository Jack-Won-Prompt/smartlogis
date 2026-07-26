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
