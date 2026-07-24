@php
    use App\Models\Organization; use App\Enums\OrgType;
    $me = auth()->user();
    $locations = $me->isHq()
        ? Organization::whereIn('org_type', [OrgType::WAREHOUSE, OrgType::HOSPITAL])->orderBy('name')->get(['id','name'])
        : collect();
@endphp

<x-app-layout title="유통기한 임박" breadcrumb="재고 / 유통기한 임박">
    <x-page-header title="유통기한 임박" subtitle="재고가 있는 Lot 중 유통기한이 임박한 항목을 조회합니다." />

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">임박 구간</label>
            <div id="seg-within" class="inline-flex rounded-lg border border-line bg-surface-1 p-1">
                <button data-v="30" class="seg rounded-md px-3 py-1.5 text-sm font-medium text-ink-500">D-30</button>
                <button data-v="60" class="seg rounded-md px-3 py-1.5 text-sm font-medium text-ink-500">D-60</button>
                <button data-v="90" class="seg rounded-md px-3 py-1.5 text-sm font-semibold text-brand-700 bg-brand-50">D-90</button>
            </div>
        </div>
        @if($locations->isNotEmpty())
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">위치</label>
            <select id="f-org" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($locations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">제품 검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 코드" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
    </x-filter-bar>

    <div id="expiry-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            let within = 90;
            function f(id){ const el=document.getElementById(id); return el?el.value:''; }
            const filters = () => ({ within, org_id: f('f-org'), keyword: f('f-keyword') });

            const grid = window.SmartGrid.create('#expiry-grid', {
                dataUrl: '{{ route('inventory.expiry.data') }}',
                readonly: true,
                params: filters,
                columns: [
                    { title:'위치', field:'org_name', minWidth:140 },
                    { title:'제품코드', field:'product_code', width:120, formatter: window.SmartGrid.mono },
                    { title:'제품명', field:'product_name', minWidth:180 },
                    { title:'Lot', field:'lot_no', width:120, formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'유통기한', field:'expiry_date', width:160, formatter: expiryChip },
                    { title:'현재고', field:'qty', hozAlign:'right', width:110, formatter:(c)=>`<span class="sg-mono">${Number(c.getValue()).toLocaleString()}</span>` },
                ],
            });

            document.querySelectorAll('#seg-within .seg').forEach(btn => btn.addEventListener('click', () => {
                within = parseInt(btn.dataset.v);
                document.querySelectorAll('#seg-within .seg').forEach(b => { b.className='seg rounded-md px-3 py-1.5 text-sm font-medium text-ink-500'; });
                btn.className='seg rounded-md px-3 py-1.5 text-sm font-semibold text-brand-700 bg-brand-50';
                grid.refresh();
            }));
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            const org=document.getElementById('f-org'); if(org) org.addEventListener('change',()=>grid.refresh());
        });

        function expiryChip(cell){
            const d = cell.getValue(); const days = cell.getData().expiry_days;
            const tone = days < 30 ? 'crit' : (days < 90 ? 'warn' : 'ok');
            const dtxt = days < 0 ? `D+${Math.abs(days)} 경과` : `D-${days}`;
            return `<span class="sg-badge sg-${tone}" style="font-family:'IBM Plex Mono'">${d} · ${dtxt}</span>`;
        }
    </script>
    @endpush
</x-app-layout>
