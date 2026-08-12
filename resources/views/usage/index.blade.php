@php use App\Enums\UsageStatus; $statuses = UsageStatus::options(); @endphp

<x-app-layout title="사용분 이력" breadcrumb="사용분 / 사용분 이력">
    <x-page-header title="사용분 이력" subtitle="등록·전송·승인·반려된 사용분 내역입니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="사용분번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <x-hospital-filter :roles="['HQ','LIFE']" />
    </x-filter-bar>

    <x-ww-grid-assets />
    <x-list-detail :url-base="url('usages')" items-label="품목">
        <x-slot:list><div id="uh-grid"></div></x-slot:list>
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
    </x-list-detail>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ DRAFT:'hold', SUBMITTED:'info', APPROVED:'ok', REJECTED:'crit' };
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#uh-grid', {
                dataUrl:'{{ route('usages.data') }}', readonly:true, screenName:'사용분이력',
                onRowDblClick:(row)=>window.dispatchEvent(new CustomEvent('detail-open',{detail:row.id})),
                params:()=>({ keyword:f('f-keyword'), status:f('f-status'), hospital_id:(document.getElementById('f-hospital')||{}).value }),
                columns:[
                    { title:'사용분번호', field:'report_no', width:200 },
                    { title:'병원', field:'hospital_name', width:150 },
                    { title:'사용일', field:'usage_date', width:120 },
                    { title:'품목', field:'items_count', editor:'number', width:80 },
                    { title:'금액', field:'total_amount', editor:'number', width:150 },
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
