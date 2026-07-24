<div wire:poll.15s>
    {{-- 필터 세그먼트 + 일괄 읽음 --}}
    <div class="mb-4 flex items-center justify-between">
        <div class="inline-flex rounded-xl border border-line bg-surface-1 p-1">
            <button wire:click="setFilter('all')"
                class="rounded-lg px-4 py-1.5 text-sm font-medium transition {{ $filter === 'all' ? 'bg-brand-50 text-brand-700' : 'text-ink-500 hover:text-ink-700' }}">
                전체
            </button>
            <button wire:click="setFilter('unread')"
                class="rounded-lg px-4 py-1.5 text-sm font-medium transition {{ $filter === 'unread' ? 'bg-brand-50 text-brand-700' : 'text-ink-500 hover:text-ink-700' }}">
                안읽음
                @if($unreadCount > 0)
                    <span class="ml-1 rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $unreadCount }}</span>
                @endif
            </button>
        </div>

        @if($unreadCount > 0)
            <button wire:click="markAllRead" class="text-sm font-semibold text-brand-600 hover:text-brand-700">모두 읽음</button>
        @endif
    </div>

    {{-- 목록 --}}
    <div class="overflow-hidden rounded-2xl border border-line bg-surface-1">
        @forelse($notifications as $noti)
            @php
                $tone = $noti->severity->tone()->value;
                $dot = ['ok'=>'bg-ok-600','warn'=>'bg-warn-600','crit'=>'bg-crit-600','info'=>'bg-info-600','hold'=>'bg-hold-600'][$tone];
            @endphp
            <div wire:key="noti-{{ $noti->id }}"
                 class="flex items-start gap-3 border-b border-line px-5 py-4 transition-colors last:border-0 {{ $noti->is_read ? 'bg-surface-1' : 'bg-brand-50/40' }}">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-ink-900">{{ $noti->title }}</p>
                        <x-status-badge :status="$noti->severity" class="!py-0.5" />
                        @unless($noti->is_read)
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-500" title="안읽음"></span>
                        @endunless
                    </div>
                    <p class="mt-0.5 truncate text-sm text-ink-600">{{ $noti->message }}</p>
                    <p class="mt-1 font-mono text-xs text-ink-300">{{ $noti->created_at->timezone('Asia/Seoul')->diffForHumans() }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if($noti->link_url)
                        <a href="{{ $noti->link_url }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-50">바로가기</a>
                    @endif
                    @unless($noti->is_read)
                        <button wire:click="markRead({{ $noti->id }})" class="rounded-lg px-2.5 py-1 text-xs font-medium text-ink-400 hover:bg-surface-2 hover:text-ink-700">읽음</button>
                    @endunless
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-ink-300">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.5 21a1.7 1.7 0 0 1-3 0"/></svg>
                </span>
                <p class="text-sm text-ink-500">{{ $filter === 'unread' ? '안읽은 알림이 없습니다.' : '알림이 없습니다.' }}</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        <x-pagination :paginator="$notifications" label="개" />
    </div>
</div>
