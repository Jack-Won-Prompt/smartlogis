{{-- 커스텀 토스트 호스트 (우하단, DESIGN.md §5.6). 네이티브 alert 미사용. --}}
<div class="pointer-events-none fixed bottom-6 right-6 z-[100] flex w-full max-w-sm flex-col gap-3"
     x-data aria-live="polite" aria-atomic="true">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="pointer-events-auto overflow-hidden rounded-xl border border-line bg-surface-1 shadow-lift"
             x-transition:enter="transition ease-brand duration-300"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-brand duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-x-3">
            <div class="flex items-start gap-3 p-4"
                 :class="{
                    'border-l-4 border-l-ok-600': t.tone === 'ok',
                    'border-l-4 border-l-crit-600': t.tone === 'crit',
                    'border-l-4 border-l-warn-600': t.tone === 'warn',
                    'border-l-4 border-l-brand-500': t.tone === 'info' || t.tone === 'brand',
                 }">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink-900" x-show="t.title" x-text="t.title"></p>
                    <p class="text-sm text-ink-600" x-text="t.message"></p>
                </div>
                <button @click="$store.toasts.remove(t.id)" class="shrink-0 text-ink-300 hover:text-ink-600" aria-label="닫기">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </template>
</div>
