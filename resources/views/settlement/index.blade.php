@php use App\Enums\SettleType; $types = SettleType::options(); @endphp

<x-app-layout title="월 정산" breadcrumb="정산 / 월 정산">
    <x-page-header title="월 정산" subtitle="사용분 승인으로 집계된 병원 매출·공급사 매입 정산입니다." />

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">정산월</label>
            <input id="f-ym" type="month" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">유형</label>
            <select id="f-type" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($types as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
    </x-filter-bar>

    <div id="st-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ OPEN:'info', CONFIRMED:'ok', CLOSED:'hold' };
            const typeTones={ SALES:'ok', PURCHASE:'warn' };
            function f(id){ return document.getElementById(id).value.replace('-','-'); }
            const grid = window.SmartGrid.create('#st-grid', {
                dataUrl:'{{ route('settlements.data') }}', readonly:true,
                params:()=>({ year_month:document.getElementById('f-ym').value, settle_type:document.getElementById('f-type').value }),
                columns:[
                    { title:'정산월', field:'year_month', width:110, formatter: window.SmartGrid.mono },
                    { title:'유형', field:'settle_type', width:90, formatter:(c)=>`<span class="sg-badge sg-${typeTones[c.getValue()]||'hold'}">${c.getData().settle_label}</span>` },
                    { title:'거래처', field:'org_name', minWidth:160 },
                    { title:'수량', field:'total_qty', width:100, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${Number(c.getValue()).toLocaleString()}</span>` },
                    { title:'금액', field:'total_amount', width:160, hozAlign:'right', formatter: window.SmartGrid.money },
                    { title:'상태', field:'status', width:100, formatter:(c)=>`<span class="sg-badge sg-${tones[c.getValue()]||'hold'}">${c.getData().status_label}</span>` },
                ],
            });
            ['f-ym','f-type'].forEach(id=>document.getElementById(id).addEventListener('change',()=>grid.refresh()));
        });
    </script>
    @endpush
</x-app-layout>
