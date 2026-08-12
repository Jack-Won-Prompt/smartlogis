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
    <div id="uh-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ DRAFT:'hold', SUBMITTED:'info', APPROVED:'ok', REJECTED:'crit' };
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#uh-grid', {
                dataUrl:'{{ route('usages.data') }}', readonly:true, screenName:'사용분이력',
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
