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

    <x-grid-assets />
    <div id="stock-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const hasOrg = document.getElementById('f-org');
            function f(id){ const el=document.getElementById(id); return el?el.value:''; }
            const filters = () => ({ org_id: f('f-org'), keyword: f('f-keyword') });

            const grid = window.SmartTUI.create('#stock-grid', {
                dataUrl: '{{ route('inventory.status.data') }}',
                readonly: true,
                params: filters,
                columns: [
                    { title:'위치', field:'org_name', minWidth:140 },
                    { title:'제품코드', field:'product_code', width:120, html: window.SmartTUI.mono },
                    { title:'제품명', field:'product_name', minWidth:180 },
                    { title:'Lot', field:'lot_no', width:120, html:(v,row)=>`<span class="stui-mono">${v}</span>` },
                    { title:'유통기한', field:'expiry_date', width:150, html: expiryChip },
                    { title:'현재고', field:'qty', align:'right', width:150, html: stockGauge },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            if(hasOrg) hasOrg.addEventListener('change',()=>grid.refresh());
        });

        // 유통기한 칩(D-day 색상)
        function expiryChip(d, row){
            if(!d) return '<span class="stui-mono" style="color:#93a4b6">—</span>';
            const days = row.expiry_days;
            const tone = days < 30 ? 'crit' : (days < 90 ? 'warn' : 'ok');
            const dtxt = days < 0 ? `D+${Math.abs(days)}` : `D-${days}`;
            return `<span class="stui-badge stui-${tone}" style="font-family:'IBM Plex Mono'">${d} · ${dtxt}</span>`;
        }
        // 재고수준 게이지(안전재고 대비)
        function stockGauge(qty, row){
            const safety = row.safety_qty || 0;
            const ratio = safety > 0 ? qty/safety : 1.5;
            const tone = ratio < 1 ? 'crit' : (ratio < 1.2 ? 'warn' : 'ok');
            const color = {crit:'#c2362b',warn:'#b4700a',ok:'#1e8a5b'}[tone];
            const pct = Math.max(0, Math.min(100, safety>0 ? (qty/(safety*1.5))*100 : 100));
            const safeTxt = safety>0 ? `<span style="color:#93a4b6;font-size:11px"> / 안전 ${safety.toLocaleString()}</span>` : '';
            return `<div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
                <span style="font-family:'IBM Plex Mono';font-weight:600;color:#101b26">${qty.toLocaleString()}</span>${safeTxt}
                <span style="display:inline-block;width:56px;height:6px;border-radius:999px;background:#eef2f5;overflow:hidden">
                    <span style="display:block;height:100%;width:${pct}%;background:${color};border-radius:999px"></span>
                </span></div>`;
        }
    </script>
    @endpush
</x-app-layout>
