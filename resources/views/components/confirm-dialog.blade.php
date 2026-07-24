{{-- 커스텀 확인 다이얼로그 (DESIGN.md §5.6). 파괴적/중요 작업 확인용. 네이티브 confirm 미사용. --}}
<div x-data x-show="$store.confirm.open" x-cloak
     class="fixed inset-0 z-[110] flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    {{-- 배경 --}}
    <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="$store.confirm.respond(false)"></div>

    {{-- 패널 --}}
    <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface-1 p-6 shadow-lift"
         x-show="$store.confirm.open"
         x-transition:enter="transition ease-brand duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         @keydown.escape.window="$store.confirm.respond(false)">
        <div class="flex items-start gap-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
                  :class="{
                    'bg-crit-100 text-crit-600': $store.confirm.payload.tone === 'crit',
                    'bg-warn-100 text-warn-600': $store.confirm.payload.tone === 'warn',
                    'bg-brand-50 text-brand-600': $store.confirm.payload.tone === 'brand',
                  }">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-display text-lg font-bold text-ink-900" x-text="$store.confirm.payload.title"></h3>
                <p class="mt-1 text-sm text-ink-600" x-text="$store.confirm.payload.message"></p>

                {{-- 대상 요약(병원명·N건·합계 등) --}}
                <template x-if="$store.confirm.payload.summary">
                    <dl class="mt-4 space-y-1.5 rounded-xl bg-surface-0 p-3">
                        <template x-for="item in $store.confirm.payload.summary" :key="item.label">
                            <div class="flex items-center justify-between text-sm">
                                <dt class="text-ink-500" x-text="item.label"></dt>
                                <dd class="font-mono font-semibold text-ink-900 tabular-nums" x-text="item.value"></dd>
                            </div>
                        </template>
                    </dl>
                </template>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button @click="$store.confirm.respond(false)"
                    class="rounded-xl border border-line bg-surface-1 px-4 py-2.5 text-sm font-semibold text-ink-600 transition hover:bg-surface-2"
                    x-text="$store.confirm.payload.cancelText"></button>
            <button @click="$store.confirm.respond(true)"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition"
                    :class="{
                        'bg-crit-600 hover:brightness-110': $store.confirm.payload.tone === 'crit',
                        'bg-warn-600 hover:brightness-110': $store.confirm.payload.tone === 'warn',
                        'bg-brand-600 hover:bg-brand-700': $store.confirm.payload.tone === 'brand',
                    }"
                    x-text="$store.confirm.payload.confirmText"></button>
        </div>
    </div>
</div>
