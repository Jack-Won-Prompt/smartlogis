@php
    use App\Models\Organization; use App\Enums\OrgType;
    $me = auth()->user();
    $locations = $me->isHq()
        ? Organization::whereIn('org_type', [OrgType::WAREHOUSE, OrgType::HOSPITAL])->orderBy('name')->get(['id','name'])
        : collect();
@endphp

<x-app-layout title="재고 현황" breadcrumb="재고 / 재고 현황">
    <x-page-header title="재고 현황" subtitle="위치·제품·Lot 단위 현재고와 유통기한을 조회합니다." />

    <x-filter-bar class="mt-6">
        @if($locations->isNotEmpty())
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">위치</label>
            <select id="f-org" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($locations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">제품 검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 코드" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-slot:actions>
            <a href="#" onclick="event.preventDefault()" class="hidden"></a>
        </x-slot:actions>
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="stock-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const hasOrg = document.getElementById('f-org');
            function f(id){ const el=document.getElementById(id); return el?el.value:''; }
            const filters = () => ({ org_id: f('f-org'), keyword: f('f-keyword') });

            const grid = window.WWGrid.connect('#stock-grid', {
                dataUrl: '{{ route('inventory.status.data') }}',
                readonly: true, screenName: '재고현황',
                paged: true, pageSize: 30,
                params: filters,
                columns: [
                    { title:'위치', field:'org_name', width:150 },
                    { title:'제품코드', field:'product_code', width:120 },
                    { title:'제품명', field:'product_name', width:220 },
                    { title:'Lot', field:'lot_no', width:120 },
                    { title:'유통기한', field:'expiry_date', width:130 },
                    { title:'안전재고', field:'safety_qty', editor:'number', width:110 },
                    { title:'현재고', field:'qty', editor:'number', width:110 },
                    { title:'예약', field:'reserved_qty', width:90 },
                    { title:'가용재고', field:'available_qty', width:110 },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            if(hasOrg) hasOrg.addEventListener('change',()=>grid.refresh());
        });
    </script>
    @endpush
</x-app-layout>
