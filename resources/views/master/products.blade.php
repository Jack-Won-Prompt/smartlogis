@php
    use App\Models\Organization;
    use App\Enums\StorageType;
    $suppliers = Organization::where('org_type', 'SUPPLIER')->orderBy('name')->get(['id', 'name']);
    $storageTypes = StorageType::options();
@endphp

<x-app-layout title="제품 마스터" breadcrumb="기준정보 / 제품 마스터">
    <x-page-header title="제품 마스터" subtitle="셀을 클릭해 바로 수정하고, 행을 추가·삭제하며 엑셀로 일괄 관리합니다." />

    {{-- 필터 --}}
    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 코드 / GTIN / 보험코드"
                   class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">공급사</label>
            <select id="f-supplier" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">보관유형</label>
            <select id="f-storage" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>
                @foreach($storageTypes as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">사용여부</label>
            <select id="f-active" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option><option value="1">사용</option><option value="0">중지</option>
            </select>
        </div>
        <x-slot:actions>
            <button id="f-reset" class="rounded-lg border border-line bg-surface-1 px-4 py-2 text-sm font-medium text-ink-600 hover:bg-surface-0">초기화</button>
        </x-slot:actions>
    </x-filter-bar>

    {{-- 툴바 --}}
    <div class="mb-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2" x-data="{ open:false, importing:false, failKey:null }">
            <a href="{{ route('master.products.export') }}" id="btn-export" class="btn-ghost !py-2 !text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                엑셀 다운로드
            </a>
            <button @click="open=!open" class="btn-ghost !py-2 !text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 8l5-5 5 5M12 3v12"/></svg>
                엑셀 업로드
            </button>
            <div x-show="open" x-cloak @click.outside="open=false" class="absolute z-30 mt-24 w-80 rounded-xl border border-line bg-surface-1 p-4 shadow-lift">
                <p class="text-sm font-semibold text-ink-900">제품 엑셀 업로드</p>
                <p class="mt-1 text-xs text-ink-500">양식을 내려받아 작성 후 업로드하세요. 제품코드가 같으면 갱신됩니다.</p>
                <a href="{{ route('master.products.template') }}" class="mt-3 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">양식 다운로드</a>
                <input type="file" id="import-file" accept=".xlsx,.xls,.csv" class="mt-3 block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700">
                <button @click="importExcel($event, this)" :disabled="importing"
                        class="mt-3 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                    <span x-text="importing ? '처리 중…' : '업로드 실행'"></span>
                </button>
                <template x-if="failKey">
                    <a :href="`{{ url('master/products/failures') }}/${failKey}`" class="mt-2 block rounded-lg border border-crit-600/30 bg-crit-100 px-3 py-2 text-center text-xs font-semibold text-crit-600">실패 행 다운로드</a>
                </template>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button id="btn-delete" class="btn-ghost !py-2 !text-sm !text-crit-600 !ring-crit-600/20 hover:!bg-crit-100">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                선택 삭제
            </button>
            <button id="btn-add" class="btn-primary !py-2 !text-sm" data-magnetic>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                행 추가
            </button>
        </div>
    </div>

    {{-- 그리드 --}}
    <div id="product-grid"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const supplierMap = @json($suppliers->pluck('name', 'id'));
            const storageMap = @json($storageTypes);
            const storageTones = { ROOM: 'info', COLD: 'hold', FROZEN: 'hold' };

            const filters = () => ({
                keyword: document.getElementById('f-keyword').value,
                supplier_id: document.getElementById('f-supplier').value,
                storage_type: document.getElementById('f-storage').value,
                is_active: document.getElementById('f-active').value,
            });

            const grid = window.SmartGrid.create('#product-grid', {
                dataUrl: '{{ route('master.products.data') }}',
                createUrl: '{{ route('master.products.store') }}',
                updateUrl: (id) => `{{ url('master/products') }}/${id}`,
                deleteUrl: '{{ route('master.products.bulkDestroy') }}',
                params: filters,
                defaults: { product_code: '', product_name: '', supplier_id: '', gtin: '', storage_type: 'ROOM', sales_price: 0, is_active: true },
                columns: [
                    { title: '제품코드', field: 'product_code', editor: 'input', width: 130, formatter: window.SmartGrid.mono },
                    { title: '제품명', field: 'product_name', editor: 'input', minWidth: 200 },
                    { title: '공급사', field: 'supplier_id', editor: 'list', editorParams: { values: supplierMap }, width: 170,
                      formatter: (c) => supplierMap[c.getValue()] ?? c.getData().supplier_name ?? '' },
                    { title: 'GTIN', field: 'gtin', editor: 'input', width: 150, formatter: window.SmartGrid.mono },
                    { title: '보관', field: 'storage_type', editor: 'list', editorParams: { values: storageMap }, width: 110,
                      formatter: (c) => { const v=c.getValue(); return `<span class="sg-badge sg-${storageTones[v]||'hold'}">${storageMap[v]||v}</span>`; } },
                    { title: '매출가', field: 'sales_price', editor: 'number', hozAlign: 'right', width: 130, formatter: window.SmartGrid.money },
                    { title: '사용', field: 'is_active', editor: 'tickCross', hozAlign: 'center', width: 90,
                      formatter: (c) => c.getValue() ? '<span class="sg-badge sg-ok">사용</span>' : '<span class="sg-badge sg-hold">중지</span>' },
                ],
            });

            // 필터 이벤트
            let t;
            document.getElementById('f-keyword').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => grid.refresh(), 350); });
            ['f-supplier', 'f-storage', 'f-active'].forEach(id => document.getElementById(id).addEventListener('change', () => grid.refresh()));
            document.getElementById('f-reset').addEventListener('click', () => {
                document.getElementById('f-keyword').value = '';
                ['f-supplier', 'f-storage', 'f-active'].forEach(id => document.getElementById(id).value = '');
                grid.refresh();
            });

            document.getElementById('btn-add').addEventListener('click', () => grid.addBlankRow());
            document.getElementById('btn-delete').addEventListener('click', () => grid.deleteSelected());
        });

        // 엑셀 업로드
        async function importExcel(e, ctx) {
            const input = document.getElementById('import-file');
            if (!input.files.length) { window.toast('파일을 선택하세요.', 'warn'); return; }
            ctx.importing = true;
            const fd = new FormData();
            fd.append('file', input.files[0]);
            try {
                const res = await fetch('{{ route('master.products.import') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                ctx.failKey = data.failKey;
                window.toast(data.message, data.failed ? 'warn' : 'ok', '엑셀 업로드');
                window.dispatchEvent(new CustomEvent('grid-refresh'));
                document.querySelector('#product-grid')._tabulator?.setData?.();
                location.reload();
            } catch (err) {
                window.toast('업로드 중 오류가 발생했습니다.', 'crit');
            } finally {
                ctx.importing = false;
            }
        }
    </script>
    @endpush
</x-app-layout>
