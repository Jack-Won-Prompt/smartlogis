<x-app-layout title="부족/납품" breadcrumb="공급사 / 부족·납품">
    <x-page-header title="부족 품목 / 납품 요청" subtitle="자사 제품 중 병원 안전재고가 미달된 항목입니다. 창고 납품이 필요합니다." />

    <x-ww-grid-assets />
    <div id="short-grid" class="mt-6"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.WWGrid.connect('#short-grid', {
                dataUrl:'{{ route('supplier.shortages.data') }}', readonly:true, screenName:'부족품목',
                columns:[
                    { title:'병원', field:'hospital_name', width:160 },
                    { title:'제품코드', field:'product_code', width:120 },
                    { title:'제품명', field:'product_name', width:220 },
                    { title:'현재고', field:'onhand', editor:'number', width:100 },
                    { title:'안전재고', field:'safety_qty', editor:'number', width:100 },
                    { title:'부족', field:'shortage', editor:'number', width:110 },
                    { title:'권장 납품', field:'reorder_qty', editor:'number', width:110 },
                ],
            });
        });
    </script>
    @endpush
</x-app-layout>
