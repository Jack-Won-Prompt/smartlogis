<x-app-layout title="상품분석" breadcrumb="리포트 / 상품분석">
    <x-page-header title="상품분석" subtitle="품목별 사용량·매출·현재고·회전(사용량/현재고)을 분석합니다. (승인 사용분 기준)" />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색(품목명/코드)</label>
            <input id="f-keyword" type="text" placeholder="품목명 또는 코드" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">시작일(사용일)</label>
            <input id="f-from" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">종료일</label>
            <input id="f-to" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-slot:actions><button id="f-apply" class="btn-primary !py-2 !text-sm">조회</button></x-slot:actions>
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="pa-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const f = id => document.getElementById(id).value;
            const grid = window.WWGrid.connect('#pa-grid', {
                dataUrl: '{{ route('reports.product-analysis.data') }}', readonly: true, screenName: '상품분석',
                params: () => ({ keyword: f('f-keyword'), date_from: f('f-from'), date_to: f('f-to') }),
                columns: [
                    { title:'제품코드', field:'product_code', width:120 },
                    { title:'제품명', field:'product_name', width:240 },
                    { title:'사용량', field:'used_qty', width:100 },
                    { title:'매출', field:'amount', width:140 },
                    { title:'현재고', field:'stock', width:100 },
                    { title:'회전(사용/재고)', field:'turnover', width:130 },
                ],
            });
            document.getElementById('f-apply').addEventListener('click', () => grid.refresh());
            let t; document.getElementById('f-keyword').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => grid.refresh(), 350); });
        });
    </script>
    @endpush
</x-app-layout>
