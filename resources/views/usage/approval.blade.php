@php use App\Enums\UsageStatus; $statuses = UsageStatus::options(); @endphp

<x-app-layout title="사용분 승인" breadcrumb="사용분 / 사용분 승인">
    <x-page-header title="사용분 승인" subtitle="전송된 사용분을 승인하면 병원 재고가 차감되고 정산에 반영됩니다. (행 더블클릭 → 상세)" />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="사용분번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="SUBMITTED">전송됨(승인대기)</option>
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <x-hospital-filter :roles="['HQ']" />
    </x-filter-bar>

    <x-ww-grid-assets />
    <x-list-detail :url-base="url('usages')" items-label="품목">
        <x-slot:list><div id="ap-grid"></div></x-slot:list>
        <x-slot:info>
            <div class="flex justify-between"><span class="text-ink-400">병원</span><span class="font-medium text-ink-800" x-text="doc.hospital_name"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">사용일</span><span x-text="doc.usage_date"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">합계금액</span><span class="font-mono font-semibold text-ink-900" x-text="'₩'+Number(doc.total_amount||0).toLocaleString()"></span></div>
            <template x-if="doc.reject_reason"><div class="rounded-lg bg-crit-100 px-3 py-2 text-xs text-crit-600"><b>반려사유</b> <span x-text="doc.reject_reason"></span></div></template>
        </x-slot:info>
        <x-slot:items>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">부서</th><th class="py-2 text-right">수량</th><th class="py-2 text-right">금액</th></tr></thead>
                <tbody>
                    <template x-for="(it,i) in (doc.items||[])" :key="i">
                        <tr class="border-b border-line/60">
                            <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                            <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                            <td class="py-2 text-xs text-ink-500" x-text="it.dept||'—'"></td>
                            <td class="py-2 text-right font-mono" x-text="Number(it.qty).toLocaleString()"></td>
                            <td class="py-2 text-right font-mono" x-text="'₩'+Number(it.amount||0).toLocaleString()"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </x-slot:items>
        <x-slot:actions>
            <template x-if="doc.status==='SUBMITTED' && !rejecting">
                <div class="flex gap-2">
                    <button @click="rejecting=true" class="rounded-xl border border-crit-600/30 px-4 py-2 text-sm font-semibold text-crit-600 hover:bg-crit-100">반려</button>
                    <button @click="act('{{ url('usages') }}/'+doc.id+'/approve',{confirm:{title:'사용분 승인',message:doc.report_no+' 승인 시 재고가 차감되고 정산에 반영됩니다.',tone:'brand',confirmText:'승인'}})" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">승인</button>
                </div>
            </template>
            <template x-if="rejecting">
                <div class="w-full space-y-2">
                    <textarea x-model="reason" rows="2" placeholder="반려 사유 (예: 수량 상이, Lot 불일치)" class="w-full rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25"></textarea>
                    <div class="flex justify-end gap-2">
                        <button @click="rejecting=false" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-ink-600 hover:bg-surface-2">취소</button>
                        <button @click="reason.trim() ? act('{{ url('usages') }}/'+doc.id+'/reject',{body:{reason}}) : window.toast('반려 사유를 입력하세요.','warn')" :disabled="saving" class="rounded-xl bg-crit-600 px-5 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50">반려 확정</button>
                    </div>
                </div>
            </template>
            <template x-if="doc.id && doc.status!=='SUBMITTED' && !rejecting"><span class="text-xs text-ink-400">전송됨(승인대기) 상태만 승인·반려할 수 있습니다.</span></template>
        </x-slot:actions>
    </x-list-detail>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#ap-grid', {
                dataUrl:'{{ route('usages.data') }}', readonly:true, screenName:'사용분승인',
                onRowDblClick:(row)=>window.dispatchEvent(new CustomEvent('detail-open',{detail:row.id})),
                params:()=>({ keyword:f('f-keyword'), status:f('f-status'), hospital_id:(document.getElementById('f-hospital')||{}).value }),
                columns:[
                    { title:'사용분번호', field:'report_no', width:200 },
                    { title:'병원', field:'hospital_name', width:150 },
                    { title:'사용일', field:'usage_date', width:120 },
                    { title:'품목', field:'items_count', editor:'number', width:80 },
                    { title:'금액', field:'total_amount', editor:'number', width:140 },
                    { title:'상태', field:'status_label', width:110 },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>grid.refresh());
            document.getElementById('f-hospital')?.addEventListener('change',()=>grid.refresh());
        });
    </script>
    @endpush
</x-app-layout>
