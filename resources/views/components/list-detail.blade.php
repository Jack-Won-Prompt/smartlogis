{{--
    리스트 + 상세(탭) 분할 뷰. 그리드 행 더블클릭 → 오른쪽에 상세(기본정보/품목 탭)가 열린다.
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
<div x-data="listDetail({ base: @js($urlBase) })"
     @detail-open.window="open($event.detail)"
     class="mt-4 flex flex-col gap-4 lg:flex-row">
    <div class="min-w-0 flex-1">
        {{ $list }}
    </div>

    <aside x-show="show" x-cloak
           class="shrink-0 overflow-hidden rounded-xl border border-line bg-surface-1 shadow-sm lg:w-[40%] lg:min-w-[360px]">
        <div class="flex items-start justify-between gap-2 border-b border-line px-4 py-3">
            <div class="min-w-0">
                <p class="text-[11px] text-ink-400">상세 정보</p>
                <h3 class="truncate font-display text-base font-bold text-ink-900" x-text="docNo()"></h3>
            </div>
            <div class="flex items-center gap-2">
                <span x-show="doc.status_label" class="rounded-full bg-surface-2 px-2.5 py-1 text-[11px] font-semibold text-ink-600" x-text="doc.status_label"></span>
                <button @click="close()" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
        </div>

        <div class="flex gap-1 border-b border-line px-3 pt-2">
            <button @click="tab='info'" :class="tab==='info' ? 'border-brand-500 text-brand-600' : 'border-transparent text-ink-400 hover:text-ink-600'" class="border-b-2 px-3 pb-2 text-sm font-semibold">기본정보</button>
            <button @click="tab='items'" :class="tab==='items' ? 'border-brand-500 text-brand-600' : 'border-transparent text-ink-400 hover:text-ink-600'" class="border-b-2 px-3 pb-2 text-sm font-semibold">{{ $itemsLabel }} (<span x-text="(doc.items||[]).length"></span>)</button>
        </div>

        <div class="max-h-[64vh] overflow-y-auto px-4 py-4">
            <div x-show="loading" class="py-8 text-center text-sm text-ink-400">불러오는 중…</div>

            <div x-show="!loading && tab==='info'" x-cloak class="space-y-2.5 text-sm">
                {{ $info }}
            </div>

            <div x-show="!loading && tab==='items'" x-cloak>
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
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3">
            {{ $actions }}
        </div>
        @endisset
    </aside>
</div>

@once
@push('scripts')
<script>
    // 공용 상세 패널 Alpine 팩토리 — 더블클릭으로 받은 id 로 상세를 불러와 탭으로 표시.
    window.listDetail = function (cfg) {
        return {
            show: false, tab: 'info', loading: false, saving: false, doc: {},
            rejecting: false, reason: '',   // 반려 등 사유 입력 액션 공용
            selItems: [],                   // 품목 선택(피킹 등) — 미배정 품목 기본 선택
            open(id) {
                this.show = true; this.tab = 'info'; this.loading = true; this.doc = {}; this.rejecting = false; this.reason = ''; this.selItems = [];
                fetch(cfg.base + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json()).then((d) => { this.doc = d; this.selItems = (d.items || []).filter((it) => !it.lot_assigned).map((it) => it.id); })
                    .catch(() => { window.toast?.('상세를 불러오지 못했습니다.', 'crit'); this.show = false; })
                    .finally(() => { this.loading = false; });
            },
            toggleItem(id) { const i = this.selItems.indexOf(id); if (i >= 0) this.selItems.splice(i, 1); else this.selItems.push(id); },
            isSel(id) { return this.selItems.includes(id); },
            close() { this.show = false; },
            reload() { if (this.doc.id) this.open(this.doc.id); },
            docNo() { const d = this.doc; return d.inbound_no || d.outbound_no || d.report_no || d.return_no || d.no || ''; },
            csrf() { return document.querySelector('meta[name=csrf-token]').content; },
            // 공용 액션: path 로 요청 → 성공 시 토스트 + 그리드 새로고침(+옵션에 따라 닫기/재로딩)
            async act(path, opts = {}) {
                const { method = 'POST', confirm = null, body = null, closeAfter = true } = opts;
                if (confirm) { const ok = await window.confirmDialog(confirm); if (!ok) return; }
                this.saving = true;
                try {
                    const r = await fetch(path, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' }, body: body ? JSON.stringify(body) : null });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok) { window.toast?.(d.message || '처리되었습니다.', 'ok'); window.__smartGridRefresh?.(); if (closeAfter) this.show = false; else this.reload(); }
                    else window.toast?.(d.message || (d.errors && Object.values(d.errors)[0][0]) || '처리에 실패했습니다.', 'crit');
                } catch (e) { window.toast?.('오류가 발생했습니다.', 'crit'); } finally { this.saving = false; }
            },
        };
    };
</script>
@endpush
@endonce
