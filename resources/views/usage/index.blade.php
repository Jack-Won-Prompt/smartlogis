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
    </x-filter-bar>

    <x-grid-assets />
    <div id="uh-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ DRAFT:'hold', SUBMITTED:'info', APPROVED:'ok', REJECTED:'crit' };
            function f(id){ return document.getElementById(id).value; }
            const grid = window.SmartTUI.create('#uh-grid', {
                dataUrl:'{{ route('usages.data') }}', readonly:true,
                params:()=>({ keyword:f('f-keyword'), status:f('f-status') }),
                columns:[
                    { title:'사용분번호', field:'report_no', width:200, html: window.SmartTUI.mono },
                    { title:'병원', field:'hospital_name', minWidth:140 },
                    { title:'사용일', field:'usage_date', width:120, html:(v,row)=>`<span class="stui-mono">${v}</span>` },
                    { title:'품목', field:'items_count', width:70, align:'right', html:(v,row)=>`<span class="stui-mono">${v}</span>` },
                    { title:'금액', field:'total_amount', width:140, align:'right', html: window.SmartTUI.money },
                    { title:'상태', field:'status', width:110, html:(v,row)=>`<span class="stui-badge stui-${tones[v]||'hold'}">${row.status_label}</span>` },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>grid.refresh());
        });
    </script>
    @endpush
</x-app-layout>
