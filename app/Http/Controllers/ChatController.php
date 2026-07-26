<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ConversationRead;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 사용자 간 채팅(1:1/그룹) — 어떤 사용자든 검색해 대화를 시작할 수 있다.
 * (supportworks 웹 메시지 기능 이식: 프로젝트/회사 제한 없이 전사 사용자 대상)
 */
class ChatController extends Controller
{
    public function index(): View
    {
        return view('chat.index', [
            'conversations' => $this->conversationsFor((int) auth()->id()),
            'conversation' => null,
        ]);
    }

    public function show(Conversation $conversation): View
    {
        $userId = (int) auth()->id();
        abort_unless($this->isParticipant($conversation, $userId), 403);

        $conversation->load(['participants.organization', 'messages.sender', 'messages.replyTo.sender']);

        $now = now();
        $conversation->participants()->updateExistingPivot($userId, ['last_read_at' => $now]);
        $this->safeBroadcast(fn () => broadcast(new ConversationRead($conversation->id, $userId, $now->toIso8601String())));

        return view('chat.index', [
            'conversations' => $this->conversationsFor($userId),
            'conversation' => $conversation,
        ]);
    }

    /** 사용자 검색(자동완성) — 전사 모든 사용자(본인 제외). */
    public function userSearch(Request $request): JsonResponse
    {
        $kw = trim($request->string('q')->toString());
        $meId = (int) auth()->id();

        $users = User::query()
            ->where('id', '!=', $meId)
            ->when($kw !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")
                ->orWhere('email', 'like', "%{$kw}%")
                ->orWhere('login_id', 'like', "%{$kw}%")))
            ->with('organization:id,name')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'email', 'role', 'org_id']);

        return response()->json($users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role->label(),
            'org' => $u->organization->name,
        ])->all());
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) auth()->id();
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id|different:'.$userId,
            'body' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480',
        ]);

        $receiver = User::findOrFail((int) $request->integer('receiver_id'));

        $conv = Conversation::findBetween($userId, $receiver->id);
        if ($conv === null) {
            $conv = Conversation::create(['is_group' => false]);
            $conv->participants()->attach([$userId, $receiver->id], ['last_read_at' => null]);
        }

        if ($request->filled('body') || $this->hasFiles($request)) {
            $this->sendMessages($request, $conv->id, $userId);
        }
        $conv->participants()->updateExistingPivot($userId, ['last_read_at' => now()]);

        return redirect()->route('chat.show', $conv);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $userId = (int) auth()->id();
        $request->validate([
            'name' => 'required|string|max:100',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
            'body' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480',
        ]);

        $conv = Conversation::create(['name' => $request->string('name')->toString(), 'is_group' => true]);

        $memberIds = $request->collect('member_ids')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $userId)
            ->prepend($userId)
            ->unique()
            ->values();

        $conv->participants()->attach($memberIds->all(), ['last_read_at' => null]);

        if ($request->filled('body') || $this->hasFiles($request)) {
            $this->sendMessages($request, $conv->id, $userId);
        }
        $conv->participants()->updateExistingPivot($userId, ['last_read_at' => now()]);

        return redirect()->route('chat.show', $conv);
    }

    public function reply(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $userId = (int) auth()->id();
        abort_unless($this->isParticipant($conversation, $userId), 403);

        $request->validate([
            'body' => 'nullable|string|max:8000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        if (! $request->filled('body') && ! $this->hasFiles($request)) {
            return $request->expectsJson() ? response()->json(['error' => 'empty'], 422) : back();
        }

        $messages = $this->sendMessages($request, $conversation->id, $userId);
        $conversation->participants()->updateExistingPivot($userId, ['last_read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'messages' => array_map(fn (Message $m) => $m->toChatArray(), $messages)])
            : redirect()->route('chat.show', $conversation);
    }

    public function invite(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = (int) auth()->id();
        abort_unless($conversation->is_group, 422, '그룹 채팅에만 초대할 수 있습니다.');
        abort_unless($this->isParticipant($conversation, $userId), 403);

        $request->validate([
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $existingIds = $conversation->participants->pluck('id');
        $toAttach = $request->collect('member_ids')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $existingIds->contains($id))
            ->unique()
            ->values();

        if ($toAttach->isEmpty()) {
            return response()->json(['ok' => false, 'message' => '초대할 사용자가 없습니다(이미 참여 중).'], 422);
        }

        $conversation->participants()->attach(
            $toAttach->mapWithKeys(fn ($id) => [$id => ['last_read_at' => null]])->all()
        );

        return response()->json(['ok' => true, 'added' => $toAttach->count(), 'message' => $toAttach->count().'명을 초대했습니다.']);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $userId = (int) auth()->id();
        $conversation = $message->conversation;
        abort_unless($this->isParticipant($conversation, $userId), 403);
        abort_unless($message->sender_id === $userId, 403, '본인이 보낸 메시지만 수정할 수 있습니다.');
        abort_if($message->isDeleted(), 410, '삭제된 메시지는 수정할 수 없습니다.');

        $data = $request->validate(['body' => 'required|string|max:8000']);
        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['ok' => false, 'message' => '내용을 입력하세요.'], 422);
        }

        $message->update(['body' => $body, 'edited_at' => now()]);
        $this->safeBroadcast(fn () => broadcast(new MessageUpdated($message)));

        return response()->json(['ok' => true, 'id' => $message->id, 'body' => $message->body, 'edited' => true]);
    }

    public function destroy(Message $message): JsonResponse
    {
        $userId = (int) auth()->id();
        $conversation = $message->conversation;
        abort_unless($this->isParticipant($conversation, $userId), 403);
        abort_unless($message->sender_id === $userId, 403, '본인이 보낸 메시지만 삭제할 수 있습니다.');

        if (! $message->isDeleted()) {
            $message->update(['deleted_at' => now()]);
            $this->safeBroadcast(fn () => broadcast(new MessageDeleted($message)));
        }

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    public function leave(Conversation $conversation): RedirectResponse
    {
        $userId = (int) auth()->id();
        abort_unless($this->isParticipant($conversation, $userId), 403);

        $conversation->participants()->detach($userId);
        if ($conversation->participants()->count() === 0) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        return redirect()->route('chat.index');
    }

    // ── 내부 헬퍼 ─────────────────────────────────────────

    private function isParticipant(Conversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('user_id', $userId)->exists();
    }

    private function hasFiles(Request $request): bool
    {
        return count(array_filter((array) $request->file('files', []))) > 0;
    }

    /** @return Collection<int, Conversation> */
    private function conversationsFor(int $userId): Collection
    {
        return Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants', 'lastMessage.sender'])
            ->get()
            ->sortByDesc(fn (Conversation $c) => optional($c->lastMessage)->created_at)
            ->values();
    }

    /** @return list<Message> */
    private function sendMessages(Request $request, int $convId, int $senderId): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files', [])));

        if ($files === []) {
            return [$this->createMessage($request, $convId, $senderId, null, true)];
        }

        $created = [];
        foreach ($files as $i => $file) {
            $created[] = $this->createMessage($request, $convId, $senderId, $file, $i === 0);
        }

        return $created;
    }

    private function createMessage(Request $request, int $convId, int $senderId, ?UploadedFile $file, bool $isFirst): Message
    {
        $data = [
            'conversation_id' => $convId,
            'sender_id' => $senderId,
            'body' => $isFirst ? $request->string('body')->toString() : '',
            'reply_to_id' => $isFirst ? ($request->integer('reply_to_id') ?: null) : null,
        ];

        if ($file !== null) {
            $data['file_path'] = $file->store('messages', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
        }

        $message = Message::create($data);
        $this->safeBroadcast(fn () => broadcast(new MessageSent($message)));

        return $message;
    }

    private function safeBroadcast(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('[chat broadcast] '.$e->getMessage());
        }
    }
}
