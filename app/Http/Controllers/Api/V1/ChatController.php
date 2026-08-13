<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 메세지(채팅) — 모바일.
 *
 * 웹과 같은 conversations / messages 테이블을 그대로 쓴다. 다른 점은 두 가지다.
 *  - 화면이 좁아 대화방 목록과 대화방을 별도 화면으로 나눈다(웹은 좌우 분할).
 *  - 위로 스크롤 로딩을 **id 커서**로 단순화했다. 웹은 "1주일치" 단위로 끊지만
 *    모바일은 스크롤이 짧아 건수 기준이 예측 가능하다.
 *
 * 파일 전송은 아직 없다(웹에서 올린 첨부는 file_url 로 내려가 볼 수 있다).
 */
class ChatController extends ApiController
{
    /** 대화방 목록 — 마지막 메시지와 안 읽은 수. */
    public function conversations(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $rows = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants:id,name', 'lastMessage.sender:id,name'])
            ->get()
            // 마지막 메시지가 최근인 방부터. 메시지가 없는 방(막 만든 1:1)은 뒤로 민다.
            ->sortByDesc(fn (Conversation $c) => $c->lastMessage?->created_at)
            ->values();

        return response()->json([
            'data' => $rows->map(fn (Conversation $c) => [
                'id' => $c->id,
                'name' => $c->displayName($userId),
                'is_group' => $c->is_group,
                'member_names' => $c->memberNames($userId),
                'member_count' => $c->participants->count(),
                'unread' => $c->unreadCount($userId),
                'last_message' => $c->lastMessage === null ? null : [
                    'body' => $this->preview($c->lastMessage),
                    'sender_name' => $c->lastMessage->sender?->name,
                    'is_mine' => $c->lastMessage->sender_id === $userId,
                    'created_at' => $c->lastMessage->created_at?->toIso8601String(),
                ],
            ])->all(),
        ]);
    }

    /** 하단 탭·상단 배지에 쓰는 전체 안 읽은 수. */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $total = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->sum(fn (Conversation $c) => $c->unreadCount($userId));

        return response()->json(['count' => $total]);
    }

    /**
     * 대화방 메시지 — 최신 N건부터, 위로 스크롤 시 `before_id` 커서로 이어 받는다.
     *
     * 열면 읽음 처리까지 한다(웹 show 와 동일). 별도 read 호출을 강제하면
     * 앱이 한 번 더 왕복해야 하고, 그 사이 배지가 남는다.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $conversation = $this->participating($request, $id);
        $userId = (int) $request->user()->id;

        $size = min(max($request->integer('size', 30), 1), 100);
        $beforeId = $request->integer('before_id');

        $batch = $conversation->messages()
            ->with(['sender:id,name', 'replyTo.sender:id,name'])
            ->when($beforeId > 0, fn ($q) => $q->where('id', '<', $beforeId))
            ->reorder()->orderByDesc('id')
            ->limit($size)
            ->get()
            // 화면은 과거 → 최신 순으로 그린다.
            ->sortBy('id')
            ->values();

        $oldestId = $batch->min('id');
        $hasMore = $oldestId !== null
            && $conversation->messages()->where('id', '<', $oldestId)->exists();

        // 첫 페이지를 열 때만 읽음 처리한다(위로 스크롤은 읽음과 무관).
        if ($beforeId <= 0) {
            $this->markRead($conversation, $userId);
        }

        return response()->json([
            'data' => $batch->map(fn (Message $m) => $m->toChatArray())->all(),
            'has_more' => $hasMore,
            'oldest_id' => $oldestId,
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->displayName($userId),
                'is_group' => $conversation->is_group,
                'member_count' => $conversation->participants->count(),
            ],
        ]);
    }

    /** 메시지 전송. */
    public function send(Request $request, int $id): JsonResponse
    {
        $conversation = $this->participating($request, $id);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ], [], ['body' => '내용']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'body' => $validated['body'],
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        // 웹 화면이 열려 있으면 실시간으로 뜨도록 같은 이벤트를 쏜다.
        $this->safeBroadcast(fn () => broadcast(new MessageSent($message)));

        // 보낸 사람 기준으로는 읽은 상태다.
        $this->markRead($conversation, $userId, broadcastRead: false);

        return response()->json(['data' => $message->toChatArray()], 201);
    }

    /** 읽음 처리만 따로(목록에서 스와이프 등). */
    public function read(Request $request, int $id): JsonResponse
    {
        $conversation = $this->participating($request, $id);
        $this->markRead($conversation, (int) $request->user()->id);

        return $this->ok('읽음 처리했습니다.');
    }

    /** 내 메시지 삭제 — 웹과 같이 tombstone(내용만 지우고 자리는 남긴다). */
    public function destroyMessage(Request $request, int $messageId): JsonResponse
    {
        $message = Message::find($messageId);

        abort_if($message === null, 404, '메시지를 찾을 수 없습니다.');
        abort_unless(
            $message->sender_id === (int) $request->user()->id,
            403,
            '내가 보낸 메시지만 삭제할 수 있습니다.',
        );

        $message->update(['deleted_at' => now(), 'body' => null]);

        return $this->ok('삭제했습니다.');
    }

    /** 대화 상대 검색 — 전사 사용자(본인 제외). */
    public function users(Request $request): JsonResponse
    {
        $kw = trim($request->string('keyword')->toString());
        $meId = (int) $request->user()->id;

        $users = User::query()
            ->where('id', '!=', $meId)
            ->when($kw !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")
                ->orWhere('email', 'like', "%{$kw}%")
                ->orWhere('login_id', 'like', "%{$kw}%")))
            ->with('organization:id,name')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'role', 'org_id']);

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role_label' => $u->role->label(),
                'org_name' => $u->organization?->name,
            ])->all(),
        ]);
    }

    /** 1:1 대화 시작 — 이미 있으면 그 방을 돌려준다. */
    public function startDirect(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'different:'.$userId],
        ], [], ['user_id' => '상대']);

        $otherId = (int) $validated['user_id'];

        $conversation = Conversation::findBetween($userId, $otherId);

        if ($conversation === null) {
            $conversation = DB::transaction(function () use ($userId, $otherId) {
                $c = Conversation::create(['is_group' => false]);
                $c->participants()->attach([$userId, $otherId], ['last_read_at' => null]);

                return $c;
            });
        }

        return response()->json(['id' => $conversation->id], 201);
    }

    // ────────────────────────────────────────────────────────────── 내부

    /** 참여자가 아니면 대화방에 접근할 수 없다. */
    private function participating(Request $request, int $id): Conversation
    {
        $conversation = Conversation::with('participants:id,name')->find($id);

        abort_if($conversation === null, 404, '대화방을 찾을 수 없습니다.');
        abort_unless(
            $conversation->participants->contains('id', (int) $request->user()->id),
            403,
            '참여하지 않은 대화방입니다.',
        );

        return $conversation;
    }

    private function markRead(Conversation $conversation, int $userId, bool $broadcastRead = true): void
    {
        $now = now();
        $conversation->participants()->updateExistingPivot($userId, ['last_read_at' => $now]);

        if ($broadcastRead) {
            $this->safeBroadcast(fn () => broadcast(
                new ConversationRead($conversation->id, $userId, $now->toIso8601String()),
            ));
        }
    }

    /** 목록에 한 줄로 보여줄 마지막 메시지 요약. */
    private function preview(Message $message): string
    {
        if ($message->isDeleted()) {
            return '삭제된 메시지';
        }
        if (($message->body ?? '') !== '') {
            return $message->body;
        }

        return $message->isImage() ? '사진' : '파일';
    }

    /**
     * 브로드캐스트 실패가 메시지 전송을 막지 않게 한다.
     * (Pusher 미설정 환경에서도 앱은 폴링으로 동작해야 한다.)
     */
    private function safeBroadcast(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('[chat broadcast] '.$e->getMessage());
        }
    }
}
