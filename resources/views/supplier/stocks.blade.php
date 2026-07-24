<x-app-layout title="자사 재고" breadcrumb="공급사 / 자사 재고">
    <x-page-header title="자사 제품 병원별 재고" subtitle="자사 제품이 각 거점병원 선납창고에 보유된 현재고입니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 병원명" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
    </x-filter-bar>

    <x-grid-assets />
    <div id="sup-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const grid = window.SmartTUI.create('#sup-grid', {
                dataUrl:'{{ route('supplier.stocks.data') }}', readonly:true,
                params:()=>({ keyword:document.getElementById('f-keyword').value }),
                columns:[
                    { title:'병원', field:'hospital_name', minWidth:150 },
                    { title:'제품코드', field:'product_code', width:120, html: window.SmartTUI.mono },
                    { title:'제품명', field:'product_name', minWidth:180 },
                    { title:'Lot', field:'lot_no', width:120, html:(v,row)=>`<span class="stui-mono">${v}</span>` },
                    { title:'유통기한', field:'expiry_date', width:130, html:(v,row)=>`<span class="stui-mono">${v||'—'}</span>` },
                    { title:'현재고', field:'qty', width:110, align:'right', html:(v,row)=>`<span class="stui-mono" style="font-weight:600">${Number(v).toLocaleString()}</span>` },
                ],
            });
            let t; document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
        });
    </script>
    @endpush
</x-app-layout>
