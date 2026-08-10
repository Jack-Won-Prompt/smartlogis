<x-app-layout title="입고 검수" breadcrumb="입출고 / 입고 검수">
    <x-page-header title="입고 검수" subtitle="입고 예정 문서를 검수하고 확정하면 창고 재고에 반영됩니다." />

    <x-ww-grid-assets />
    <div id="recv-grid" class="mt-6" x-data></div>

    {{-- 검수 모달 --}}
    <div x-data="receiving()" @recv-open.window="load($event.detail)" x-show="show" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <div>
                    <h3 class="font-display text-lg font-bold text-ink-900" x-text="doc.inbound_no"></h3>
                    <p class="text-xs text-ink-500"><span x-text="doc.from_name"></span> → <span x-text="doc.to_name"></span></p>
                </div>
                <button @click="show=false" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <x-brand.flow-rail current="WAREHOUSE" variant="light" />

                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500">
                        <th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">유통기한</th><th class="py-2 text-right">수량</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="it in doc.items" :key="it.id">
                            <tr class="border-b border-line/60">
                                <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span><span class="ml-1 font-mono text-xs text-ink-300" x-text="it.product_code"></span></td>
                                <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                                <td class="py-2 font-mono text-xs" x-text="it.expiry_date || '—'"></td>
                                <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty).toLocaleString()"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <button @click="show=false" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-ink-600 hover:bg-surface-2">닫기</button>
                <button type="button" @click="window.open(`/inbounds/${doc.id}/labels`,'_blank')" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-brand-600 hover:bg-brand-50">🏷 라벨 출력</button>
                <button @click="confirm()" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">입고 확정</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let recvGrid;
        window.addEventListener('DOMContentLoaded', () => {
            const statusTones = { PLANNED:'hold', RECEIVING:'info' };
            recvGrid = window.WWGrid.connect('#recv-grid', {
                dataUrl: '{{ route('inbounds.data') }}', readonly:true, screenName:'입고검수',
                onRowClick:(row)=>window.dispatchEvent(new CustomEvent('recv-open',{detail:row.id})),
                params: () => ({ receivable: 1 }),
                columns: [
                    { title:'입고번호', field:'inbound_no', width:170 },
                    { title:'공급사', field:'from_name', width:150 },
                    { title:'입고창고', field:'to_name', width:150 },
                    { title:'예정일', field:'planned_date', width:120 },
                    { title:'품목', field:'items_count', editor:'number', width:80 },
                    { title:'상태', field:'status_label', width:110 },
                ],
            });
        });

        function receiving(){
            return {
                show:false, saving:false, doc:{items:[]},
                async load(id){
                    const res = await fetch(`{{ url('inbounds') }}/${id}`, { headers:{Accept:'application/json'} });
                    this.doc = await res.json(); this.show=true;
                },
                async confirm(){
                    this.saving=true;
                    const ok = await window.confirmDialog({ title:'입고 확정', message:`${this.doc.inbound_no} 을(를) 확정하고 재고에 반영할까요?`, tone:'brand', confirmText:'확정' });
                    if(!ok){ this.saving=false; return; }
                    const res = await fetch(`{{ url('inbounds') }}/${this.doc.id}/confirm`, { method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,Accept:'application/json'} });
                    const data = await res.json();
                    this.saving=false;
                    if(res.ok){ window.toast(data.message,'ok','입고 확정'); this.show=false; recvGrid.refresh(); }
                    else { window.toast(data.message||'확정 실패','crit'); }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
