@php
    use Illuminate\Support\Facades\Route;
    $me = auth()->user();
    $meId = (int) $me->id;
    $pusherKey = config('broadcasting.connections.pusher.key');
    $pusherCluster = config('broadcasting.connections.pusher.options.cluster');
    // 초기 대화 메시지(활성 대화가 있으면)
    $activeId = $conversation?->id;
    // 1:1 읽음 표시 초기값 — 상대의 last_read 가 내 마지막 메시지 이후이면 '읽음'
    $otherReadInit = false;
    if ($conversation && ! $conversation->is_group) {
        $myLast = $conversation->messages->where('sender_id', $meId)->last();
        $oReadAt = $conversation->otherParticipant($meId)?->pivot?->last_read_at;
        $otherReadInit = (bool) ($myLast && $oReadAt && $oReadAt >= $myLast->created_at);
    }
@endphp

<x-app-layout title="채팅" breadcrumb="채팅">
    @push('head')
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
        {{-- 채팅용 raw CSS(Tailwind 퍼지/미컴파일과 무관하게 강제). --}}
        <style>
            /* 액션바(답장/수정/삭제) — 각 글에 마우스오버 시에만 노출 */
            .chat-msg-actions { display: none; }
            .group:hover .chat-msg-actions { display: flex; }
            @media (hover: none) { .chat-msg-actions { display: flex; } } /* 터치기기는 hover 불가 → 상시 */
            /* 액션 버튼 — 흰 배경에 옅은 회색이라 안 보이던 문제 → 진한 회색 + hover 강조 */
            .chat-act-btn { display: grid; place-items: center; height: 1.5rem; width: 1.5rem;
                            border-radius: .375rem; color: #475569; font-size: .95rem; line-height: 1; }
            .chat-act-btn:hover { background: #eef2f7; color: #1e293b; }
            .chat-act-del { color: #dc2626; }
            .chat-act-del:hover { background: #fee2e2; color: #b91c1c; }
            /* 첨부 이미지 썸네일 크기 고정(max-h-52 미컴파일 대비) */
            .chat-img { max-width: 260px; max-height: 260px; width: auto; height: auto;
                        border-radius: .5rem; object-fit: contain; display: block; cursor: zoom-in; }
            /* 이미지 원본 팝업 오버레이(bg-navy/70·z-[60] 미컴파일 대비) */
            .chat-lightbox { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center;
                             padding: 1rem; background: rgba(11, 26, 51, .78); }
            /* 수정 팝오버 — 버튼 옆에 떠서 편집(배경 어둡게 하지 않음, 바깥 클릭으로 닫기) */
            .chat-pop-backdrop { position: fixed; inset: 0; z-index: 59; background: transparent; }
            .chat-editpop { position: fixed; z-index: 60; width: 320px; max-width: calc(100vw - 16px);
                            top: 0; left: 0; }
        </style>
    @endpush

    <div class="flex h-[calc(100vh-8.5rem)] overflow-hidden rounded-2xl border border-line bg-white shadow-sm"
         x-data="chatApp({
            meId: {{ $meId }},
            activeId: {{ $activeId ?? 'null' }},
            pusherKey: @js($pusherKey),
            pusherCluster: @js($pusherCluster),
            convChannels: @js($conversations->pluck('id')),
            othersReadInit: {{ $otherReadInit ? 'true' : 'false' }},
            hasMoreOlderInit: {{ ($hasMoreOlder ?? false) ? 'true' : 'false' }},
            oldestIdInit: {{ $oldestId ?? 'null' }},
            olderUrl: @js($conversation ? route('chat.older', $conversation) : ''),
         })" x-init="init()">

        {{-- ── 좌: 대화 목록 ─────────────────────────────── --}}
        <aside class="flex w-72 shrink-0 flex-col border-r border-line">
            <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                <h2 class="text-sm font-bold text-ink-900">대화</h2>
                <div class="flex gap-1">
                    <button @click="openUserSearch()" title="새 대화" class="grid h-8 w-8 place-items-center rounded-lg text-brand-600 hover:bg-brand-50">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                    <button @click="openGroup()" title="그룹 만들기" class="grid h-8 w-8 place-items-center rounded-lg text-ink-500 hover:bg-surface-2">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse($conversations as $c)
                    @php $unread = $c->unreadCount($meId); $last = $c->lastMessage; @endphp
                    <a href="{{ route('chat.show', $c) }}"
                       data-conv="{{ $c->id }}"
                       class="flex items-center gap-3 border-b border-line/60 px-4 py-3 transition-colors hover:bg-surface-1 {{ $activeId === $c->id ? 'bg-brand-50' : '' }}">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-gradient text-sm font-bold text-white">
                            {{ mb_substr($c->displayName($meId), 0, 1) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-ink-900">{{ $c->displayName($meId) }}{{ $c->is_group ? ' ('.($c->participants->count()).')' : '' }}</span>
                                <span class="shrink-0 text-[11px] text-ink-300">{{ optional($last?->created_at)->timezone('Asia/Seoul')?->format('m/d H:i') }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-ink-400">{{ $last ? ($last->isDeleted() ? '(삭제된 메시지)' : ($last->body ?: ($last->file_name ? '📎 '.$last->file_name : ''))) : '대화를 시작하세요' }}</span>
                                <span data-unread="{{ $c->id }}" class="shrink-0 rounded-full bg-brand-500 px-1.5 text-[10px] font-bold text-white {{ $unread ? '' : 'hidden' }}">{{ $unread ?: '' }}</span>
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-10 text-center text-sm text-ink-300">대화가 없습니다.<br>+ 를 눌러 시작하세요.</p>
                @endforelse
            </div>
        </aside>

        {{-- ── 우: 대화 내용 ─────────────────────────────── --}}
        <section class="flex min-w-0 flex-1 flex-col bg-surface-0">
            @if($conversation)
                <header class="flex items-center justify-between gap-3 border-b border-line bg-white px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-ink-900">{{ $conversation->displayName($meId) }}</p>
                        <p class="truncate text-xs text-ink-400">
                            @if($conversation->is_group)
                                {{ $conversation->participants->pluck('name')->join(', ') }}
                            @else
                                {{ $conversation->otherParticipant($meId)?->role->label() }} · {{ $conversation->otherParticipant($meId)?->organization?->name }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if($conversation->is_group)
                            <button @click="openInvite()" class="rounded-lg px-3 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50">초대</button>
                        @endif
                        <form method="POST" action="{{ route('chat.leave', $conversation) }}" onsubmit="return false" x-ref="leaveForm">@csrf @method('DELETE')</form>
                        <button @click="leave()" class="rounded-lg px-3 py-1.5 text-xs font-medium text-crit-600 hover:bg-crit-100">나가기</button>
                    </div>
                </header>

                <div x-ref="msgList" class="min-h-0 flex-1 space-y-1 overflow-y-auto px-5 py-4">
                    <div x-ref="olderTop">
                        <div x-show="loadingOlder" x-cloak class="py-2 text-center text-xs text-ink-400">이전 대화를 불러오는 중…</div>
                    </div>
                    @php $prevDay = null; @endphp
                    @foreach($conversation->messages as $m)
                        @php $day = optional($m->created_at)?->timezone('Asia/Seoul')?->format('Y-m-d'); @endphp
                        @if($day !== $prevDay)
                            <div class="my-3 flex items-center justify-center">
                                <span class="rounded-full bg-surface-2 px-3 py-1 text-[11px] text-ink-400">{{ optional($m->created_at)?->timezone('Asia/Seoul')?->format('Y년 n월 j일') }}</span>
                            </div>
                            @php $prevDay = $day; @endphp
                        @endif
                        @include('chat.partials.message', ['m' => $m, 'meId' => $meId])
                    @endforeach
                    @unless($conversation->is_group)
                        <div x-show="othersRead" x-cloak class="pr-1 pt-0.5 text-right text-[11px] font-medium text-brand-500">읽음</div>
                    @endunless
                </div>

                {{-- 답장 미리보기 --}}
                <div x-show="replyTo" x-cloak class="flex items-center justify-between gap-2 border-t border-line bg-surface-1 px-5 py-2 text-xs">
                    <span class="min-w-0 truncate text-ink-500"><b x-text="replyTo?.name"></b>: <span x-text="replyTo?.body"></span></span>
                    <button @click="replyTo=null" class="text-ink-400 hover:text-ink-700">✕</button>
                </div>

                <form @submit.prevent="send()" @paste="onPaste($event)" class="flex items-end gap-2 border-t border-line bg-white px-4 py-3">
                    <label class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-lg text-ink-400 hover:bg-surface-2" title="파일 첨부">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        <input type="file" multiple class="hidden" x-ref="files" @change="onFiles()">
                    </label>
                    <div class="min-w-0 flex-1">
                        <div x-show="pendingFiles.length" x-cloak class="mb-1 flex flex-wrap gap-1">
                            <template x-for="(f,i) in pendingFiles" :key="i">
                                <span class="inline-flex items-center gap-1 rounded bg-surface-2 px-2 py-0.5 text-[11px] text-ink-600">
                                    📎 <span x-text="f.name || '이미지'"></span>
                                    <button type="button" @click="pendingFiles.splice(i,1)" class="text-ink-400 hover:text-crit-600">✕</button>
                                </span>
                            </template>
                        </div>
                        <textarea x-ref="body" x-model="body" rows="1" placeholder="메시지 입력 (Enter 전송, Shift+Enter 줄바꿈, 이미지 붙여넣기 지원)"
                                  @keydown.enter="if(!$event.shiftKey){$event.preventDefault(); send();}"
                                  class="block w-full resize-none rounded-xl border-line bg-surface-1 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25"></textarea>
                    </div>
                    <button type="submit" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-600 text-white hover:bg-brand-700" title="전송">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    </button>
                </form>
            @else
                <div class="flex h-full flex-col items-center justify-center gap-3 text-ink-300">
                    <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>
                    <p class="text-sm">왼쪽에서 대화를 선택하거나 <b class="text-brand-600">+</b> 로 새 대화를 시작하세요.</p>
                </div>
            @endif
        </section>

        {{-- ── 사용자 검색 모달(새 1:1 대화) ─────────────── --}}
        <div x-show="ui==='search'" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-4" @click.self="ui=null">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-lift">
                <h3 class="mb-3 text-base font-bold text-ink-900">새 대화 — 사용자 검색</h3>
                <input x-ref="usearch" x-model="q" @input.debounce.250ms="searchUsers()" placeholder="이름 / 이메일 / 아이디"
                       class="w-full rounded-lg border-line bg-surface-1 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
                    <template x-for="u in results" :key="u.id">
                        <button @click="startChat(u.id)" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-surface-1">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-gradient text-xs font-bold text-white" x-text="u.name?.[0]"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-ink-900" x-text="u.name"></span><span class="block truncate text-xs text-ink-400"><span x-text="u.role"></span> · <span x-text="u.org"></span></span></span>
                        </button>
                    </template>
                    <p x-show="!results.length" class="py-6 text-center text-sm text-ink-300">검색어를 입력하세요.</p>
                </div>
                <div class="mt-4 text-right"><button @click="ui=null" class="rounded-lg px-4 py-2 text-sm text-ink-500 hover:bg-surface-2">닫기</button></div>
            </div>
        </div>

        {{-- ── 그룹 만들기 / 초대 모달 ────────────────────── --}}
        <div x-show="ui==='group' || ui==='invite'" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-4" @click.self="ui=null">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-lift">
                <h3 class="mb-3 text-base font-bold text-ink-900" x-text="ui==='group' ? '그룹 만들기' : '멤버 초대'"></h3>
                <input x-show="ui==='group'" x-model="groupName" placeholder="그룹 이름" class="mb-2 w-full rounded-lg border-line bg-surface-1 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <input x-model="q" @input.debounce.250ms="searchUsers()" placeholder="사용자 검색(이름/이메일/아이디)" class="w-full rounded-lg border-line bg-surface-1 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <div x-show="picked.length" class="mt-2 flex flex-wrap gap-1">
                    <template x-for="p in picked" :key="p.id">
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-700"><span x-text="p.name"></span><button @click="unpick(p.id)" class="text-brand-400">✕</button></span>
                    </template>
                </div>
                <div class="mt-2 max-h-56 space-y-1 overflow-y-auto">
                    <template x-for="u in results" :key="u.id">
                        <button @click="pick(u)" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-surface-1">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-gradient text-xs font-bold text-white" x-text="u.name?.[0]"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-ink-900" x-text="u.name"></span><span class="block truncate text-xs text-ink-400"><span x-text="u.role"></span> · <span x-text="u.org"></span></span></span>
                        </button>
                    </template>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="ui=null" class="rounded-lg px-4 py-2 text-sm text-ink-500 hover:bg-surface-2">취소</button>
                    <button @click="ui==='group' ? createGroup() : doInvite()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" x-text="ui==='group' ? '만들기' : '초대'"></button>
                </div>
            </div>
        </div>

        {{-- ── 이미지 원본 팝업(라이트박스) ─────────────── --}}
        <div x-show="lightbox" x-cloak class="chat-lightbox"
             @click.self="lightbox=null" @keydown.escape.window="lightbox=null">
            <div class="flex max-h-[92vh] max-w-[92vw] flex-col items-center gap-3">
                <img :src="lightbox?.url" :alt="lightbox?.name" class="max-h-[80vh] max-w-[92vw] rounded-lg object-contain shadow-lift">
                <div class="flex items-center gap-2">
                    <a :href="lightbox?.url" :download="lightbox?.name"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        다운로드
                    </a>
                    <button @click="lightbox=null" class="rounded-lg bg-white/90 px-4 py-2 text-sm font-medium text-ink-700 hover:bg-white">닫기</button>
                </div>
            </div>
        </div>

        {{-- ── 메시지 수정 팝오버(수정 버튼 옆에 떠서 편집) ── --}}
        <template x-if="editing">
            <div>
                <div class="chat-pop-backdrop" @click="editing=null"></div>
                <div data-edit-pop class="chat-editpop rounded-xl border border-line bg-white p-3 shadow-lift" @keydown.escape.window="editing=null">
                    <div class="mb-1.5 text-xs font-semibold text-ink-500">메시지 수정</div>
                    <textarea data-edit-body x-model="editing.body" rows="3"
                              @keydown.enter="if(!$event.shiftKey){$event.preventDefault(); saveEdit();}"
                              placeholder="Enter 저장, Shift+Enter 줄바꿈"
                              class="block w-full resize-none rounded-lg border-line bg-surface-1 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25"></textarea>
                    <div class="mt-2 flex justify-end gap-1.5">
                        <button @click="editing=null" class="rounded-lg px-3 py-1.5 text-xs text-ink-500 hover:bg-surface-2">취소</button>
                        <button @click="saveEdit()" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">저장</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- 숨은 폼(1:1 시작 / 그룹 생성 서버 제출용) --}}
        <form x-ref="startForm" method="POST" action="{{ route('chat.store') }}" class="hidden">@csrf<input type="hidden" name="receiver_id" x-ref="startReceiver"></form>
        <form x-ref="groupForm" method="POST" action="{{ route('chat.group') }}" class="hidden">@csrf<input type="hidden" name="name" x-ref="groupNameInput"><div x-ref="groupMembers"></div></form>
    </div>

    @push('scripts')
    <script>
        window.chatApp = function (cfg) {
            return {
                meId: cfg.meId, activeId: cfg.activeId, ui: null,
                q: '', results: [], picked: [], groupName: '',
                body: '', pendingFiles: [], replyTo: null,
                othersRead: cfg.othersReadInit || false,
                pusher: null, lightbox: null, editing: null,
                hasMoreOlder: cfg.hasMoreOlderInit || false, oldestId: cfg.oldestIdInit || null, loadingOlder: false,

                init() {
                    this.fitHeight();
                    window.addEventListener('resize', () => this.fitHeight());
                    this.scrollBottom();
                    this.setupPusher(cfg.pusherKey, cfg.pusherCluster, cfg.convChannels || []);
                    this.$watch('body', () => this.autoGrow());
                    // 위로 스크롤 시 이전 대화(1주일치씩) 추가 로드
                    const ml = this.$refs.msgList;
                    if (ml) ml.addEventListener('scroll', () => { if (ml.scrollTop < 80) this.loadOlder(); });
                    // 메시지 액션(답장/수정/삭제) 이벤트 위임 — 서버 렌더 + 동적 추가 메시지 모두 동작
                    const list = this.$refs.msgList;
                    if (list) list.addEventListener('click', (e) => {
                        // 이미지 클릭 → 원본 팝업(라이트박스)
                        const img = e.target.closest('img.chat-img');
                        if (img) { this.openImage(img.getAttribute('src'), img.dataset.name || ''); return; }
                        const btn = e.target.closest('[data-act]');
                        if (!btn) return;
                        const wrap = btn.closest('[data-msg]');
                        if (!wrap) return;
                        const id = parseInt(wrap.dataset.msg, 10);
                        const act = btn.dataset.act;
                        if (act === 'reply') this.setReply(id, wrap.dataset.sender || '', (wrap.querySelector('[data-body]')?.textContent || wrap.dataset.file || '').trim());
                        else if (act === 'edit') this.editMsg(id, btn);
                        else if (act === 'delete') this.delMsg(id);
                    });
                },
                autoGrow() {
                    const el = this.$refs.body;
                    if (!el) return;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 140) + 'px';
                },
                // 채팅 셸을 남은 뷰포트 높이에 맞춰 채움(하단 공백/스크롤 제거, 프레임 모드 포함)
                fitHeight() {
                    const el = this.$el;
                    const top = el.getBoundingClientRect().top;
                    const main = el.closest('main');
                    const padB = main ? (parseFloat(getComputedStyle(main).paddingBottom) || 0) : 0;
                    el.style.height = Math.max(360, Math.floor(window.innerHeight - top - padB)) + 'px';
                },
                csrf() { return document.querySelector('meta[name=csrf-token]').content; },

                setupPusher(key, cluster, convIds) {
                    if (!key || !window.Pusher) return;
                    this.pusher = new Pusher(key, {
                        cluster: cluster,
                        forceTLS: true,
                        authEndpoint: '{{ url('/broadcasting/auth') }}',
                        auth: { headers: { 'X-CSRF-TOKEN': this.csrf() } },
                    });
                    // 내 모든 대화방 구독(목록 실시간 갱신 + 활성방 메시지)
                    (convIds || []).forEach((id) => {
                        const ch = this.pusher.subscribe('private-conversation.' + id);
                        ch.bind('message.sent', (d) => this.onMessage(d));
                        ch.bind('message.updated', (d) => this.onUpdated(d));
                        ch.bind('message.deleted', (d) => this.onDeleted(d));
                        ch.bind('conversation.read', (d) => this.onRead(d));
                    });
                },

                onMessage(d) {
                    if (d.sender_id === this.meId) return; // 내가 보낸 건 전송 응답에서 이미 표시
                    this.bumpList(d);
                    if (d.conversation_id !== this.activeId) {
                        // 다른 대화 → 토스트 알림
                        if (window.toast) window.toast((d.sender_name || '') + ': ' + (d.body || (d.file_name ? '📎 ' + d.file_name : '')), 'info', '새 메시지');
                        return;
                    }
                    if (document.querySelector('[data-msg="' + d.id + '"]')) return; // 중복 방지
                    const near = this.nearBottom();
                    this.appendMessage(d);
                    if (near) this.scrollBottom();
                    this.markRead();
                },
                // 상대가 내 메시지를 읽음 → '읽음' 표시
                onRead(d) {
                    if (d.conversation_id === this.activeId && d.user_id !== this.meId) this.othersRead = true;
                },
                onUpdated(d) {
                    const el = document.querySelector('[data-msg="' + d.id + '"] [data-body]');
                    if (el) { el.textContent = d.body; const tag = el.parentElement.querySelector('[data-edited]'); if (tag) tag.classList.remove('hidden'); }
                },
                onDeleted(d) { this.markDeleted(d.id); },
                markDeleted(id) {
                    const bubble = document.querySelector('[data-msg="' + id + '"] [data-bubble]');
                    if (bubble) bubble.innerHTML = '<span class="text-sm italic text-ink-300">삭제된 메시지</span>';
                },

                // 메시지 DOM 요소 생성(신규/실시간/이전로드 공용). 반환된 요소를 호출측이 append/insert.
                buildMessageEl(d) {
                    const mine = d.sender_id === this.meId;
                    const wrap = document.createElement('div');
                    wrap.setAttribute('data-msg', d.id);
                    wrap.setAttribute('data-sender', d.sender_name || '');
                    wrap.setAttribute('data-file', d.file_name || '');
                    wrap.className = 'group relative flex ' + (mine ? 'justify-end' : 'justify-start');
                    let inner = '';
                    if (!mine) inner += '<span class="mr-2 mt-1 grid h-7 w-7 shrink-0 place-items-center self-end rounded-full bg-brand-gradient text-[10px] font-bold text-white">' + this.esc((d.sender_name||'?')[0]) + '</span>';
                    let bubble = '<div data-bubble class="relative rounded-2xl px-3 py-2 text-sm ' + (mine ? 'bg-brand-600 text-white' : 'bg-white border border-line text-ink-800') + '">';
                    if (d.is_deleted) {
                        bubble += '<span class="text-sm italic ' + (mine ? 'text-white/70' : 'text-ink-300') + '">삭제된 메시지</span></div>';
                        inner += '<div class="max-w-[70%]">' + bubble + '<div class="mt-0.5 text-[10px] text-ink-300 ' + (mine?'text-right':'') + '">' + this.time(d.created_at) + '</div></div>';
                        wrap.innerHTML = inner;
                        return wrap;
                    }
                    if (!mine) bubble += '<div class="mb-0.5 text-[11px] font-semibold text-brand-600">' + this.esc(d.sender_name||'') + '</div>';
                    if (d.reply_to) bubble += '<div class="mb-1 rounded bg-black/5 px-2 py-1 text-[11px] opacity-80"><b>' + this.esc(d.reply_to.sender_name||'') + '</b> ' + this.esc((d.reply_to.body||'').slice(0,60)) + '</div>';
                    if (d.file_url) {
                        bubble += d.is_image
                            ? '<img src="'+d.file_url+'" data-name="'+this.esc(d.file_name||'')+'" class="chat-img">'
                            : '<a href="'+d.file_url+'" download="'+this.esc(d.file_name||'')+'" class="flex items-center gap-1 underline">📎 '+this.esc(d.file_name||'파일')+'</a>';
                    }
                    if (d.body) bubble += '<div data-body class="whitespace-pre-wrap break-words">' + this.esc(d.body) + '</div>';
                    bubble += '<span data-edited class="hidden ml-1 text-[10px] ' + (mine?'text-white/60':'text-ink-300') + '">(수정됨)</span>';
                    // 액션 바(답장/수정/삭제) — 위임으로 처리
                    bubble += '<div class="chat-msg-actions absolute -top-3 right-1 z-10 items-center gap-0.5 rounded-lg border border-line bg-white p-0.5 shadow-sm">'
                        + '<button type="button" data-act="reply" class="chat-act-btn" title="답장">↩</button>'
                        + (mine ? '<button type="button" data-act="edit" class="chat-act-btn" title="수정">✎</button><button type="button" data-act="delete" class="chat-act-btn chat-act-del" title="삭제">🗑</button>' : '')
                        + '</div>';
                    bubble += '</div>';
                    inner += '<div class="max-w-[70%]">' + bubble + '<div class="mt-0.5 text-[10px] text-ink-300 ' + (mine?'text-right':'') + '">' + this.time(d.created_at) + '</div></div>';
                    wrap.innerHTML = inner;
                    return wrap;
                },
                appendMessage(d) {
                    this.$refs.msgList.appendChild(this.buildMessageEl(d));
                },

                // 위로 스크롤 시 이전 메시지 1주일치(빈 주면 그 이전 50건) 추가 — 스크롤 위치 보존.
                async loadOlder() {
                    if (this.loadingOlder || !this.hasMoreOlder || !this.oldestId || !cfg.olderUrl) return;
                    this.loadingOlder = true;
                    const list = this.$refs.msgList;
                    const prevH = list.scrollHeight, prevTop = list.scrollTop;
                    try {
                        const res = await fetch(cfg.olderUrl + '?before_id=' + this.oldestId, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const d = await res.json();
                        const anchor = this.$refs.olderTop.nextSibling;   // 지시자 바로 아래(기존 메시지 위)에 순서대로 삽입
                        (d.messages || []).forEach((m) => { if (!document.querySelector('[data-msg="' + m.id + '"]')) list.insertBefore(this.buildMessageEl(m), anchor); });
                        this.hasMoreOlder = !!d.has_more;
                        if (d.oldest_id) this.oldestId = d.oldest_id;
                        list.scrollTop = prevTop + (list.scrollHeight - prevH);   // 삽입 높이만큼 보정 → 화면 튐 방지
                    } catch (e) { /* 무시 */ } finally { this.loadingOlder = false; }
                },

                bumpList(d) {
                    const link = document.querySelector('a[data-conv="' + d.conversation_id + '"]');
                    if (!link) return;
                    if (d.conversation_id !== this.activeId) {
                        const badge = document.querySelector('[data-unread="' + d.conversation_id + '"]');
                        if (badge && d.sender_id !== this.meId) { badge.textContent = (parseInt(badge.textContent||'0',10)||0)+1; badge.classList.remove('hidden'); }
                    }
                    link.parentElement.prepend(link); // 최근 대화 위로
                },

                // 메시지 전송
                async send() {
                    const text = this.body.trim();
                    if (!text && !this.pendingFiles.length) return;
                    const fd = new FormData();
                    if (text) fd.append('body', text);
                    if (this.replyTo) fd.append('reply_to_id', this.replyTo.id);
                    this.pendingFiles.forEach((f, i) => fd.append('files[]', f, f.name || ('paste-' + Date.now() + '-' + i + '.png')));
                    this.body = ''; this.pendingFiles = []; this.replyTo = null;
                    const res = await fetch('{{ $conversation ? route('chat.reply', $conversation) : '' }}', {
                        method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd,
                    });
                    if (!res.ok) {
                        const msg = res.status === 413 ? '파일 용량이 너무 큽니다(서버 업로드 한도 초과).'
                            : (res.status === 422 ? '첨부할 수 없는 파일이거나 입력값을 확인하세요.' : '전송에 실패했습니다.');
                        if (window.toast) window.toast(msg, 'crit');
                        return;
                    }
                    // 전송 응답으로 즉시 표시(Pusher 배달과 무관). Pusher echo 는 id 중복제거로 무시됨.
                    const data = await res.json().catch(() => null);
                    (data?.messages || []).forEach((m) => { if (!document.querySelector('[data-msg="' + m.id + '"]')) { this.appendMessage(m); this.bumpList(m); } });
                    this.othersRead = false; // 새 메시지는 아직 안읽음
                    this.scrollBottom();
                    this.autoGrow();
                },
                // 파일 첨부(용량 사전 체크: 20MB 초과는 서버 413 유발 → 미리 차단)
                addFile(f) {
                    const MAX = 20 * 1024 * 1024;
                    if (f.size > MAX) {
                        if (window.toast) window.toast((f.name || '파일') + ': 20MB 를 초과해 첨부할 수 없습니다.', 'warn');
                        return false;
                    }
                    this.pendingFiles.push(f);
                    return true;
                },
                onFiles() {
                    const fs = this.$refs.files?.files || [];
                    for (const f of fs) this.addFile(f);
                    if (this.$refs.files) this.$refs.files.value = ''; // 같은 파일 재선택 허용
                },
                // 클립보드 붙여넣기(Ctrl+V) 로 이미지/파일 첨부
                onPaste(e) {
                    const items = (e.clipboardData && e.clipboardData.items) || [];
                    let added = 0;
                    for (const it of items) {
                        if (it.kind === 'file') { const f = it.getAsFile(); if (f && this.addFile(f)) added++; }
                    }
                    if (added) { e.preventDefault(); if (window.toast) window.toast(added + '개 파일을 첨부했습니다. 전송을 누르세요.', 'info'); }
                },
                setReply(id, name, body) { this.replyTo = { id, name, body }; this.$refs.body?.focus(); },
                // 이미지 원본 팝업(라이트박스) — 다운로드 버튼 포함
                openImage(url, name) { if (url) this.lightbox = { url, name: name || '이미지' }; },

                async markRead() {
                    // show 재방문 없이 읽음 처리: reply 없이 last_read 갱신은 서버 show 에서. 여기선 배지만 초기화.
                    const badge = document.querySelector('[data-unread="' + this.activeId + '"]');
                    if (badge) { badge.textContent = ''; badge.classList.add('hidden'); }
                },

                // 수정 팝오버 열기 — 수정 버튼 옆에 떠서 편집. Enter 저장 / Esc·취소 닫기.
                editMsg(id, btn) {
                    const bodyEl = document.querySelector('[data-msg="'+id+'"] [data-body]');
                    let anchor = btn && btn.getBoundingClientRect ? btn.getBoundingClientRect() : null;
                    if (!anchor || anchor.width === 0) { // 버튼이 숨김/미지정이면 말풍선 기준
                        anchor = document.querySelector('[data-msg="'+id+'"] [data-bubble]').getBoundingClientRect();
                    }
                    this.editing = { id, body: bodyEl ? bodyEl.textContent : '' };
                    this.$nextTick(() => {
                        const pop = document.querySelector('[data-edit-pop]');
                        if (pop) {
                            const w = pop.offsetWidth, h = pop.offsetHeight, m = 8;
                            let left = Math.max(m, Math.min(anchor.right - w, window.innerWidth - w - m));
                            let top = anchor.bottom + 6;
                            if (top + h > window.innerHeight - m) top = Math.max(m, anchor.top - h - 6);
                            pop.style.left = left + 'px';
                            pop.style.top = top + 'px';
                        }
                        const t = document.querySelector('[data-edit-body]');
                        if (t) { t.focus(); t.select(); }
                    });
                },
                async saveEdit() {
                    if (!this.editing) return;
                    const id = this.editing.id;
                    const t = (this.editing.body || '').trim();
                    if (!t) { if (window.toast) window.toast('내용을 입력하세요.', 'warn'); return; }
                    const r = await fetch('{{ url('/chat/messages') }}/'+id, { method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf(),'Accept':'application/json'}, body: JSON.stringify({ body: t }) });
                    if (r.ok) {
                        const bodyEl = document.querySelector('[data-msg="'+id+'"] [data-body]');
                        if (bodyEl) { bodyEl.textContent = t; const tag = bodyEl.parentElement.querySelector('[data-edited]'); if (tag) tag.classList.remove('hidden'); }
                        this.editing = null;
                    } else if (window.toast) { window.toast('수정에 실패했습니다.', 'crit'); }
                },
                async delMsg(id) {
                    if (!await window.confirmDialog({ title:'메시지 삭제', message:'이 메시지를 삭제할까요?', tone:'crit', confirmText:'삭제' })) return;
                    const r = await fetch('{{ url('/chat/messages') }}/'+id, { method:'DELETE', headers:{'X-CSRF-TOKEN':this.csrf(),'Accept':'application/json'} });
                    if (r.ok) this.markDeleted(id);   // 즉시 반영(Pusher echo 는 중복 무해)
                    else if (window.toast) window.toast('삭제에 실패했습니다.', 'crit');
                },
                leave() {
                    window.confirmDialog({ title:'채팅 나가기', message:'이 대화에서 나갈까요?', tone:'crit', confirmText:'나가기' }).then(ok => { if(ok) this.$refs.leaveForm.submit ? this.$refs.leaveForm.removeAttribute('onsubmit') || this.$refs.leaveForm.submit() : null; });
                },

                // 사용자 검색 / 대화 시작
                openUserSearch() { this.ui='search'; this.q=''; this.results=[]; this.$nextTick(()=>this.$refs.usearch?.focus()); },
                openGroup() { this.ui='group'; this.q=''; this.results=[]; this.picked=[]; this.groupName=''; },
                openInvite() { this.ui='invite'; this.q=''; this.results=[]; this.picked=[]; },
                async searchUsers() {
                    const r = await fetch('{{ route('chat.users') }}?q=' + encodeURIComponent(this.q), { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
                    this.results = await r.json();
                },
                startChat(id) { this.$refs.startReceiver.value = id; this.$refs.startForm.submit(); },
                pick(u) { if(!this.picked.find(p=>p.id===u.id)) this.picked.push(u); },
                unpick(id) { this.picked = this.picked.filter(p=>p.id!==id); },
                createGroup() {
                    if (!this.groupName.trim() || !this.picked.length) { window.toast('이름과 멤버를 입력하세요.','warn'); return; }
                    this.$refs.groupNameInput.value = this.groupName;
                    this.$refs.groupMembers.innerHTML = this.picked.map(p=>'<input type="hidden" name="member_ids[]" value="'+p.id+'">').join('');
                    this.$refs.groupForm.submit();
                },
                async doInvite() {
                    if (!this.picked.length) return;
                    const r = await fetch('{{ $conversation ? route('chat.invite', $conversation) : '' }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf(),'Accept':'application/json'}, body: JSON.stringify({ member_ids: this.picked.map(p=>p.id) }) });
                    const d = await r.json(); window.toast(d.message || (r.ok?'완료':'실패'), r.ok?'ok':'crit'); this.ui=null; if(r.ok) location.reload();
                },

                esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); },
                time(iso){ if(!iso) return ''; const d=new Date(iso); return d.toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'}); },
                scrollBottom(){ this.$nextTick(()=>{ const l=this.$refs.msgList; if(l) l.scrollTop=l.scrollHeight; }); },
                nearBottom(){ const l=this.$refs.msgList; return l ? (l.scrollHeight - l.scrollTop - l.clientHeight) < 120 : true; },
            };
        };
    </script>
    @endpush
</x-app-layout>
