<x-app-layout title="부족/납품" breadcrumb="공급사 / 부족·납품">
    <x-page-header title="부족 품목 / 납품 요청" subtitle="자사 제품 중 병원 안전재고가 미달된 항목입니다. 창고 납품이 필요합니다." />

    <x-grid-assets />
    <div id="short-grid" class="mt-6"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.SmartTUI.create('#short-grid', {
                dataUrl:'{{ route('supplier.shortages.data') }}', readonly:true, pageSize:30,
                columns:[
                    { title:'병원', field:'hospital_name', minWidth:150 },
                    { title:'제품코드', field:'product_code', width:120, html: window.SmartTUI.mono },
                    { title:'제품명', field:'product_name', minWidth:180 },
                    { title:'현재고', field:'onhand', width:100, align:'right', html:(v,row)=>`<span class="stui-mono">${Number(v).toLocaleString()}</span>` },
                    { title:'안전재고', field:'safety_qty', width:100, align:'right', html:(v,row)=>`<span class="stui-mono">${Number(v).toLocaleString()}</span>` },
                    { title:'부족', field:'shortage', width:110, align:'right', html:(v,row)=>`<span class="stui-badge stui-crit" style="font-family:'IBM Plex Mono'">▼ ${Number(v).toLocaleString()}</span>` },
                    { title:'권장 납품', field:'reorder_qty', width:110, align:'right', html:(v,row)=>`<span class="stui-mono" style="font-weight:600;color:#2551c4">${Number(v).toLocaleString()}</span>` },
                ],
            });
        });
    </script>
    @endpush
</x-app-layout>
