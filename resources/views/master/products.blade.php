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
        <x-excel-tools
            :download="route('master.products.export')"
            :upload="route('master.products.import')"
            :template="route('master.products.template')"
            :failures="url('master/products/failures')"
            name="제품"
            note="양식을 내려받아 작성 후 업로드하세요. 제품코드가 같으면 갱신됩니다."
            params="{ keyword: document.getElementById('f-keyword').value, supplier_id: document.getElementById('f-supplier').value, storage_type: document.getElementById('f-storage').value, is_active: document.getElementById('f-active').value }" />

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

    {{-- 그리드 (withworks DataTables) --}}
    <x-grid-assets />
    <div class="dt-container mt-1"><table id="product-grid" style="width:100%"></table></div>

    @push('scripts')
    <script>
        jQuery(function () {
            const supplierMap = @json($suppliers->pluck('name', 'id'));
            const storageMap = @json($storageTypes);
            const storageTones = { ROOM: 'info', COLD: 'hold', FROZEN: 'hold' };

            const grid = window.SmartDT.create('#product-grid', {
                dataUrl: '{{ route('master.products.data') }}',
                createUrl: '{{ route('master.products.store') }}',
                updateUrl: (id) => `{{ url('master/products') }}/${id}`,
                deleteUrl: '{{ route('master.products.bulkDestroy') }}',
                params: () => ({
                    keyword: document.getElementById('f-keyword').value,
                    supplier_id: document.getElementById('f-supplier').value,
                    storage_type: document.getElementById('f-storage').value,
                    is_active: document.getElementById('f-active').value,
                }),
                defaults: { product_code: '', product_name: '', supplier_id: '', gtin: '', storage_type: 'ROOM', sales_price: 0, is_active: true },
                columns: [
                    { title: '제품코드', field: 'product_code', editor: 'input', render: window.SmartDT.mono },
                    { title: '제품명', field: 'product_name', editor: 'input' },
                    { title: '공급사', field: 'supplier_id', editor: 'list', values: supplierMap,
                      render: (v, row) => supplierMap[v] ?? row.supplier_name ?? '' },
                    { title: 'GTIN', field: 'gtin', editor: 'input', render: window.SmartDT.mono },
                    { title: '보관', field: 'storage_type', editor: 'list', values: storageMap,
                      render: (v) => `<span class="sdt-badge sdt-${storageTones[v]||'hold'}">${storageMap[v]||v}</span>` },
                    { title: '매출가', field: 'sales_price', editor: 'number', align: 'right', render: window.SmartDT.money },
                    { title: '사용', field: 'is_active', editor: 'tickCross',
                      render: (v) => v ? '<span class="sdt-badge sdt-ok">사용</span>' : '<span class="sdt-badge sdt-hold">중지</span>' },
                ],
            });

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
    </script>
    @endpush
</x-app-layout>
