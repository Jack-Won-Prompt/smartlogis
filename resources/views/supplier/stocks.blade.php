<x-app-layout title="자사 재고" breadcrumb="공급사 / 자사 재고">
    <x-page-header title="자사 제품 병원별 재고" subtitle="자사 제품이 각 거점병원 선납창고에 보유된 현재고입니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 병원명" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-hospital-filter :roles="['HQ','SUPPLIER']" />
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="sup-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const grid = window.WWGrid.connect('#sup-grid', {
                dataUrl:'{{ route('supplier.stocks.data') }}', readonly:true, screenName:'공급사재고',
                params:()=>({ keyword:document.getElementById('f-keyword').value, hospital_id:(document.getElementById('f-hospital')||{}).value }),
                columns:[
                    { title:'병원', field:'hospital_name', width:160 },
                    { title:'제품코드', field:'product_code', width:120 },
                    { title:'제품명', field:'product_name', width:220 },
                    { title:'Lot', field:'lot_no', width:120 },
                    { title:'유통기한', field:'expiry_date', width:130 },
                    { title:'현재고', field:'qty', editor:'number', width:110 },
                ],
            });
            let t; document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-hospital')?.addEventListener('change',()=>grid.refresh());
        });
    </script>
    @endpush
</x-app-layout>
