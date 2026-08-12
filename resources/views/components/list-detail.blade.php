{{--
    리스트 + 상세를 "화면 내부 상단 탭"으로 표시. 그리드 행 더블클릭 → 상단에 [상세 · 문서번호 ✕] 탭이
    열리고 활성화된다(여러 문서 동시 탭, 닫기 가능). 워크스페이스 탭과 같은 룩.
    사용:
      <x-list-detail :url-base="url('inbounds')" items-label="품목">
        <x-slot:list> ...그리드... </x-slot>
        <x-slot:info> ...doc.* 필드 행... </x-slot>
        <x-slot:items> ...(선택) 커스텀 품목 테이블... </x-slot>
        <x-slot:actions> ...(선택) 하단 액션 버튼... </x-slot>
      </x-list-detail>
    그리드 connect 에 onRowDblClick:(row)=>window.dispatchEvent(new CustomEvent('detail-open',{detail:row.id})) 를 연결한다.
--}}
@props(['urlBase', 'itemsLabel' => '품목'])
<div x-data="listDetail({ base: @js($urlBase) })" @detail-open.window="open($event.detail)" class="mt-4">
    {{-- 상단 탭 스트립 --}}
    <div class="flex h-11 items-stretch gap-1 overflow-x-auto rounded-t-xl border border-line bg-surface-1 px-2">
        <div @click="showList()"
             class="flex cursor-pointer items-center gap-2 self-center rounded-lg px-3 py-1.5 text-sm transition-colors"
             :class="active==='list' ? 'bg-brand-50 font-semibold text-brand-700' : 'text-ink-500 hover:bg-surface-2'">목록</div>
        <template x-for="t in tabs" :key="t.id">
            <div @click="switchTo(t.id)"
                 class="group flex cursor-pointer items-center gap-2 self-center rounded-lg px-3 py-1.5 text-sm transition-colors"
                 :class="active===t.id ? 'bg-brand-50 font-semibold text-brand-700' : 'text-ink-500 hover:bg-surface-2'">
                <span class="whitespace-nowrap">상세 · <span x-text="t.title"></span></span>
                <button @click.stop="closeTab(t.id)" class="grid h-4 w-4 place-items-center rounded text-ink-400 hover:bg-crit-100 hover:text-crit-600">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- 목록 --}}
    <div x-show="active==='list'" class="rounded-b-xl border border-t-0 border-line bg-white p-4">
        {{ $list }}
    </div>

    {{-- 상세(활성 탭) --}}
    <div x-show="active!=='list'" x-cloak class="rounded-b-xl border border-t-0 border-line bg-white">
        <div x-show="loading" class="py-12 text-center text-sm text-ink-400">불러오는 중…</div>
        <div x-show="!loading">
            <div class="flex items-center justify-between gap-2 border-b border-line px-5 py-3">
                <h3 class="font-display text-base font-bold text-ink-900" x-text="docNo()"></h3>
                <span x-show="doc.status_label" class="rounded-full bg-surface-2 px-2.5 py-1 text-[11px] font-semibold text-ink-600" x-text="doc.status_label"></span>
            </div>

            <div class="flex gap-1 border-b border-line px-4 pt-2">
                <button @click="subTab='info'" :class="subTab==='info' ? 'border-brand-500 text-brand-600' : 'border-transparent text-ink-400 hover:text-ink-600'" class="border-b-2 px-3 pb-2 text-sm font-semibold">기본정보</button>
                <button @click="subTab='items'" :class="subTab==='items' ? 'border-brand-500 text-brand-600' : 'border-transparent text-ink-400 hover:text-ink-600'" class="border-b-2 px-3 pb-2 text-sm font-semibold">{{ $itemsLabel }} (<span x-text="(doc.items||[]).length"></span>)</button>
            </div>

            <div class="px-5 py-4">
                <div x-show="subTab==='info'" class="max-w-xl space-y-2.5 text-sm">
                    {{ $info }}
                </div>
                <div x-show="subTab==='items'" x-cloak>
                    @isset($items){{ $items }}@else
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2 text-right">수량</th></tr></thead>
                            <tbody>
                                <template x-for="(it,idx) in (doc.items||[])" :key="idx">
                                    <tr class="border-b border-line/60">
                                        <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                                        <td class="py-2 font-mono text-xs" x-text="it.lot_no || '—'"></td>
                                        <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty||0).toLocaleString()"></td>
                                    </tr>
                                </template>
                                <template x-if="!(doc.items||[]).length"><tr><td colspan="3" class="py-6 text-center text-xs text-ink-400">품목이 없습니다.</td></tr></template>
                            </tbody>
                        </table>
                    @endisset
                </div>
            </div>

            @isset($actions)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-5 py-3">
                {{ $actions }}
            </div>
            @endisset
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    // 화면 내부 상단 탭 상세 패널 — 여러 문서를 탭으로 열고, doc 은 활성 탭의 문서를 가리킨다.
    window.listDetail = function (cfg) {
        return {
            tabs: [], active: 'list', loading: false, saving: false,
            subTab: 'info', selItems: [], rejecting: false, reason: '',
            get doc() { const t = this.tabs.find((x) => x.id === this.active); return t ? t.doc : {}; },
            open(id) {
                const existing = this.tabs.find((t) => t.docId === id);
                if (existing) { this.switchTo(existing.id); return; }
                const tabId = 'd' + id;
                this.tabs.push({ id: tabId, docId: id, doc: {}, title: '…' });
                this.active = tabId; this.loading = true; this.subTab = 'info'; this.rejecting = false; this.reason = ''; this.selItems = [];
                this._fetch(id, tabId);
            },
            _fetch(id, tabId) {
                fetch(cfg.base + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json()).then((d) => {
                        const t = this.tabs.find((x) => x.id === tabId); if (!t) return;
                        t.doc = d; t.title = d.inbound_no || d.outbound_no || d.report_no || d.return_no || ('#' + id);
                        if (this.active === tabId) this.selItems = (d.items || []).filter((it) => !it.lot_assigned).map((it) => it.id);
                    })
                    .catch(() => { this.closeTab(tabId); window.toast?.('상세를 불러오지 못했습니다.', 'crit'); })
                    .finally(() => { this.loading = false; });
            },
            switchTo(tabId) {
                this.active = tabId; this.subTab = 'info'; this.rejecting = false; this.reason = '';
                const t = this.tabs.find((x) => x.id === tabId);
                this.selItems = t ? (t.doc.items || []).filter((it) => !it.lot_assigned).map((it) => it.id) : [];
            },
            closeTab(tabId) {
                const i = this.tabs.findIndex((t) => t.id === tabId); if (i < 0) return;
                const wasActive = this.active === tabId;
                this.tabs.splice(i, 1);
                if (wasActive) this.active = this.tabs.length ? this.tabs[Math.max(0, i - 1)].id : 'list';
            },
            showList() { this.active = 'list'; },
            reload() { const t = this.tabs.find((x) => x.id === this.active); if (t) { this.loading = true; this._fetch(t.docId, t.id); } },
            toggleItem(id) { const i = this.selItems.indexOf(id); if (i >= 0) this.selItems.splice(i, 1); else this.selItems.push(id); },
            isSel(id) { return this.selItems.includes(id); },
            docNo() { const d = this.doc; return d.inbound_no || d.outbound_no || d.report_no || d.return_no || d.no || ''; },
            csrf() { return document.querySelector('meta[name=csrf-token]').content; },
            // 공용 액션: 성공 시 토스트 + 그리드 새로고침(+옵션에 따라 탭 닫기/재로딩)
            async act(path, opts = {}) {
                const { method = 'POST', confirm = null, body = null, closeAfter = true } = opts;
                if (confirm) { const ok = await window.confirmDialog(confirm); if (!ok) return; }
                this.saving = true;
                try {
                    const r = await fetch(path, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' }, body: body ? JSON.stringify(body) : null });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok) { window.toast?.(d.message || '처리되었습니다.', 'ok'); window.__smartGridRefresh?.(); if (closeAfter) this.closeTab(this.active); else this.reload(); }
                    else window.toast?.(d.message || (d.errors && Object.values(d.errors)[0][0]) || '처리에 실패했습니다.', 'crit');
                } catch (e) { window.toast?.('오류가 발생했습니다.', 'crit'); } finally { this.saving = false; }
            },
        };
    };
</script>
@endpush
@endonce
