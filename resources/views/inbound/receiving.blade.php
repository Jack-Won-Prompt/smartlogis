@php $me = auth()->user(); $canDelete = $me->isHq() || $me->isWarehouse(); @endphp
<x-app-layout title="입고 검수" breadcrumb="입출고 / 입고 검수">
    <x-page-header title="입고 검수" subtitle="입고 예정 문서를 검수하고 확정하면 창고 재고에 반영됩니다. (행 더블클릭 → 상세)" />

    <x-ww-grid-assets />
    <x-list-detail :url-base="url('inbounds')" items-label="품목">
        <x-slot:list><div id="recv-grid"></div></x-slot:list>
        <x-slot:info>
            <div class="flex justify-between"><span class="text-ink-400">방향</span><span class="font-medium text-ink-800" x-text="doc.direction_label"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">출발(공급사)</span><span x-text="doc.from_name"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">도착(창고)</span><span x-text="doc.to_name"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">예정일</span><span x-text="doc.planned_date || '—'"></span></div>
        </x-slot:info>
        <x-slot:items>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">유통기한</th><th class="py-2 text-right">수량</th></tr></thead>
                <tbody>
                    <template x-for="(it,i) in (doc.items||[])" :key="i">
                        <tr class="border-b border-line/60">
                            <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                            <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                            <td class="py-2 font-mono text-xs" x-text="it.expiry_date || '—'"></td>
                            <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty).toLocaleString()"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </x-slot:items>
        <x-slot:actions>
            <button type="button" @click="window.open('{{ url('inbounds') }}/'+doc.id+'/labels','_blank')" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50">🏷 라벨</button>
            @if($canDelete)
            <template x-if="doc.status!=='CONFIRMED' && doc.status!=='CANCELED'">
                <button @click="act('{{ url('inbounds') }}/'+doc.id,{method:'DELETE',confirm:{title:'입고 삭제',message:doc.inbound_no+' 입고 문서를 삭제할까요? (확정 전 문서만 삭제됩니다)',confirmText:'삭제',tone:'crit'}})" :disabled="saving" class="rounded-xl border border-crit-500/40 px-4 py-2 text-sm font-semibold text-crit-600 hover:bg-crit-100">삭제</button>
            </template>
            @endif
            <template x-if="doc.status==='PLANNED' || doc.status==='RECEIVING'">
                <button @click="act('{{ url('inbounds') }}/'+doc.id+'/confirm',{confirm:{title:'입고 확정',message:doc.inbound_no+' 을(를) 확정하고 재고에 반영할까요?',confirmText:'확정',tone:'brand'}})" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">입고 확정</button>
            </template>
            <template x-if="doc.status==='CONFIRMED'"><span class="text-xs text-ink-400">이미 확정된 입고입니다.</span></template>
        </x-slot:actions>
    </x-list-detail>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.WWGrid.connect('#recv-grid', {
                dataUrl: '{{ route('inbounds.data') }}', readonly:true, screenName:'입고검수',
                onRowDblClick:(row)=>window.dispatchEvent(new CustomEvent('detail-open',{detail:row.id})),
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
    </script>
    @endpush
</x-app-layout>
